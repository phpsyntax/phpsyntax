<?php declare(strict_types=1);

/**
 * An expression written into a string: what may stand there, the bare form and the braces.
 */

use PhpSyntax\{Builder, Node, Parser, Token, Trivia};
use PhpSyntax\Nodes\Expression\{ShellExecNode, VariableNode};
use PhpSyntax\Nodes\Scalar\{HeredocNode, InterpolatedStringNode};
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


function expression(string $code): PhpSyntax\Nodes\ExpressionNode
{
	return (new Builder)->expression($code);
}


/**
 * Replaces the variable of the name in the code and returns the code, whose tree must be the one the parser reads
 * from it, every token standing in a node of the same class, and whose trivia inside the string are marked so.
 */
function replaceVariable(string $code, string $name, string $replacement): string
{
	$file = (new Parser)->parse($code);
	$variable = $file->findFirst(VariableNode::class, fn(VariableNode $variable) => $variable->plainName === $name) ?? throw new LogicException;
	$variable->replaceWithExpression(expression($replacement));
	$result = (string) $file;
	Assert::same(describeTokens($file), describeTokens((new Parser)->parse($result)), $result);
	foreach ([...$file->find(InterpolatedStringNode::class), ...$file->find(HeredocNode::class), ...$file->find(ShellExecNode::class)] as $string) {
		foreach ($string->parts->getTokens() as $token) {
			Assert::true(array_all([...$token->leadingTrivia, ...$token->trailingTrivia], fn(Trivia $trivia) => $trivia->inInterpolation), $result);
		}
	}

	return $result;
}


/** @return list<string>  every token with the class of the node it stands in */
function describeTokens(Node $node): array
{
	return array_map(fn(Token $token) => ($token->parent ? $token->parent::class : '') . ' ' . $token->text, $node->getTokens());
}


test('what may be written in braces in a string', function () {
	foreach (['$a', '$$a', '${"a"}', '$a[0]', '$a->b', '$a?->b', '$a::$b', '$a()', '$a->b()', '$a::b()', '$a[0]->b[1]'] as $code) {
		Assert::true(expression($code)->canStandInString(), $code);
	}

	foreach (['$a[]', 'A::$b', 'f()', '(new A)->b', '$a + 1', '$a::C', '"x"', '($a)', 'A::b()', 'new A'] as $code) {
		Assert::false(expression($code)->canStandInString(), $code);
	}
});


test('in a bare place the bare form is written where it reads the same, braces otherwise', function () {
	Assert::same('<?php "x $b y";', replaceVariable('<?php "x $a y";', 'a', '$b'));
	Assert::same('<?php "x $b->c y";', replaceVariable('<?php "x $a y";', 'a', '$b->c'));
	Assert::same('<?php "x $b?->c y";', replaceVariable('<?php "x $a y";', 'a', '$b?->c'));
	Assert::same('<?php "x $b[$i] y";', replaceVariable('<?php "x $a y";', 'a', '$b[$i]'));
	Assert::same('<?php "x {$b[0]} y";', replaceVariable('<?php "x $a y";', 'a', '$b[0]'));
	Assert::same('<?php "x {$b->c()} y";', replaceVariable('<?php "x $a y";', 'a', '$b->c()'));
	Assert::same('<?php "x {$b->c->d} y";', replaceVariable('<?php "x $a y";', 'a', '$b->c->d'));
	Assert::same('<?php "{$b->c( )}";', replaceVariable('<?php "$a";', 'a', '$b->c( )'));
	Assert::same('<?php "$b$c";', replaceVariable('<?php "$a$c";', 'a', '$b'));

	// a variable the text after it would continue goes in braces
	$file = (new Parser)->parse('<?php "$a[0][1] $a->b->c";');
	$file->find(PhpSyntax\Nodes\Expression\ArrayAccessNode::class)[0]->replaceWithExpression(expression('$x'));
	$file->find(PhpSyntax\Nodes\Expression\PropertyFetchNode::class)[0]->replaceWithExpression(expression('$y'));
	Assert::same('<?php "{$x}[1] {$y}->c";', (string) $file);
});


test('in braces the expression is written as it is', function () {
	Assert::same('<?php "x {$b->c()} y";', replaceVariable('<?php "x {$a} y";', 'a', '$b->c()'));
	Assert::same('<?php "x {$b} y";', replaceVariable('<?php "x {$a} y";', 'a', '$b'));
});


test('a heredoc and a shell command take the same', function () {
	Assert::same("<?php <<<X\n  {\$b->c()} \$d\n  X;", replaceVariable("<?php <<<X\n  \$a \$d\n  X;", 'a', '$b->c()'));
	Assert::same('<?php `ls {$dir[0]}`;', replaceVariable('<?php `ls $a`;', 'a', '$dir[0]'));
});


test('an expression that cannot stand in a string is refused before anything moves', function () {
	$file = (new Parser)->parse('<?php "x $a {$b} y";');
	$code = (string) $file;
	foreach (['a', 'b'] as $name) {
		$variable = $file->findFirst(VariableNode::class, fn(VariableNode $variable) => $variable->plainName === $name) ?? throw new LogicException;
		Assert::exception(
			fn() => $variable->replaceWithExpression(expression('$x + 1')),
			InvalidArgumentException::class,
			'Expression `$x + 1` cannot be written inside a string, which takes a variable, an element, a property or a call reached from a variable.',
		);
		Assert::same($code, (string) $file);
	}
});


test('a text continuing a bare part after the replacement puts it in braces', function () {
	$file = (new Parser)->parse('<?php "$a[0]bc $a->b{c";');
	$file->find(PhpSyntax\Nodes\Expression\ArrayAccessNode::class)[0]->replaceWithExpression(expression('$x'));
	$file->find(PhpSyntax\Nodes\Expression\PropertyFetchNode::class)[0]->replaceWithExpression(expression('$y->z'));
	Assert::same('<?php "{$x}bc $y->z{c";', (string) $file);
});


test('inside a bare part a plain variable stays bare and anything else turns the part into braces', function () {
	Assert::same('<?php "$a[$j]";', replaceVariable('<?php "$a[$i]";', 'i', '$j'));
	Assert::same('<?php "$x->b";', replaceVariable('<?php "$a->b";', 'a', '$x'));
	Assert::same('<?php "$b[x]";', replaceVariable('<?php "$a[x]";', 'a', '$b'));
	Assert::same('<?php "{$a[$c->d]}";', replaceVariable('<?php "$a[$i]";', 'i', '$c->d'));
	Assert::same('<?php "{$a[$x + 1]}";', replaceVariable('<?php "$a[$i]";', 'i', '$x + 1'));
	Assert::same('<?php "{$x[0]->b}";', replaceVariable('<?php "$a->b";', 'a', '$x[0]'));
	Assert::same('<?php "{$x->y[0]}";', replaceVariable('<?php "$a[0]";', 'a', '$x->y'));
	Assert::same('<?php "{$x->y[$i]}";', replaceVariable('<?php "$a[$i]";', 'a', '$x->y'));

	$file = (new Parser)->parse('<?php "$b[-1]";');
	($file->findFirst(PhpSyntax\Nodes\Scalar\IntegerNode::class) ?? throw new LogicException)->replaceWithExpression(expression('$x'));
	Assert::same('<?php "{$b[-$x]}";', (string) $file);
});


test('what begins the braces must begin with the dollar of a variable, elsewhere in them any expression goes', function () {
	Assert::same('<?php "{$x[0]->b}";', replaceVariable('<?php "{$a->b}";', 'a', '$x[0]'));
	Assert::same('<?php "{$a[$x + 1]}";', replaceVariable('<?php "{$a[$i]}";', 'i', '$x + 1'));
	Assert::same('<?php "{$a[f( 1 )]}";', replaceVariable('<?php "{$a[$i]}";', 'i', 'f( 1 )'));
	Assert::same('<?php "{$b->c( 1 )}";', replaceVariable('<?php "{$a}";', 'a', '$b->c( 1 )'));

	foreach (['<?php "{$a->b}";', '<?php "$a->b";'] as $code) {
		foreach (['$x + 1', 'f()'] as $replacement) {
			$file = (new Parser)->parse($code);
			$variable = $file->findFirst(VariableNode::class) ?? throw new LogicException;
			Assert::exception(
				fn() => $variable->replaceWithExpression(expression($replacement)),
				InvalidArgumentException::class,
				"Expression `$replacement` cannot be written inside a string, which takes a variable, an element, a property or a call reached from a variable.",
			);
			Assert::same($code, (string) $file);
		}
	}
});


test('the name of a member or of a variable in braces takes anything else than a variable in braces', function () {
	Assert::same('<?php "{$o->{$x->y}}";', replaceVariable('<?php "{$o->$m}";', 'm', '$x->y'));
	Assert::same('<?php "{$o->{$a . "b"}}";', replaceVariable('<?php "{$o->$m}";', 'm', '$a . "b"'));
	Assert::same('<?php "{$o->$x}";', replaceVariable('<?php "{$o->$m}";', 'm', '$x'));
	Assert::same('<?php "{$o->{$x->y}()}";', replaceVariable('<?php "{$o->$m()}";', 'm', '$x->y'));
	Assert::same('<?php "{${$x->y}}";', replaceVariable('<?php "{$$m}";', 'm', '$x->y'));
	Assert::same('<?php "{$o->$m->{$x->y}}";', replaceVariable('<?php "{$o->$m->$n}";', 'n', '$x->y'));
	Assert::same('<?php "{$o->{$x->y}->$n}";', replaceVariable('<?php "{$o->$m->$n}";', 'm', '$x->y'));
	Assert::same('<?php "${${$x->y}}";', replaceVariable('<?php "${$$m}";', 'm', '$x->y'));
});


test('the name of ${name} is refused, an expression in ${expr} goes as anywhere', function () {
	Assert::same('<?php "${$x + 1}";', replaceVariable('<?php "${$a}";', 'a', '$x + 1'));
	Assert::same('<?php "${a[$x + 1]}";', replaceVariable('<?php "${a[$i]}";', 'i', '$x + 1'));

	foreach (['<?php "${a}";', '<?php "${a[0]}";'] as $code) {
		$file = (new Parser)->parse($code);
		$variable = $file->findFirst(VariableNode::class) ?? throw new LogicException;
		Assert::exception(
			fn() => $variable->replaceWithExpression(expression('$x')),
			InvalidArgumentException::class,
			'The name `a` of `${...}` cannot be replaced by an expression, which would name a variable variable there.',
		);
		Assert::same($code, (string) $file);
	}
});


test('braces after a dollar of the text are refused, an escaped one takes them', function () {
	Assert::same('<?php "$$b";', replaceVariable('<?php "$$a";', 'a', '$b'));
	Assert::same('<?php "\${$b[0]}";', replaceVariable('<?php "\$$a";', 'a', '$b[0]'));

	foreach ([['<?php "$$a";', 'a', '$b[0]'], ['<?php "$$a[$i]";', 'i', '$c->d']] as [$code, $name, $replacement]) {
		$file = (new Parser)->parse($code);
		$variable = $file->findFirst(VariableNode::class, fn(VariableNode $variable) => $variable->plainName === $name) ?? throw new LogicException;
		Assert::exception(
			fn() => $variable->replaceWithExpression(expression($replacement)),
			InvalidArgumentException::class,
			'Braces cannot be written right after the dollar of the text `$`, which would read as `${`.',
		);
		Assert::same($code, (string) $file);
	}
});


test('checkReplaceWithExpression() refuses what the write refuses, and lets through what it writes', function () {
	$standsInString = 'cannot be written inside a string, which takes a variable, an element, a property or a call reached from a variable.';
	$cases = [
		['<?php "x $a y";', 'a', '$x + 1', 'Expression `$x + 1` ' . $standsInString],
		['<?php "{$a->b}";', 'a', 'f()', 'Expression `f()` ' . $standsInString],
		['<?php "$a->b";', 'a', 'f()', 'Expression `f()` ' . $standsInString],
		[
			'<?php "${a}";',
			'a',
			'$x',
			'The name `a` of `${...}` cannot be replaced by an expression, which would name a variable variable there.',
		],
		['<?php "$$a";', 'a', '$b[0]', 'Braces cannot be written right after the dollar of the text `$`, which would read as `${`.'],
		['<?php "$$a[$i]";', 'i', '$c->d', 'Braces cannot be written right after the dollar of the text `$`, which would read as `${`.'],
	];
	foreach ($cases as [$code, $name, $replacement, $message]) {
		$file = (new Parser)->parse($code);
		$variable = $file->findFirst(VariableNode::class, fn(VariableNode $variable) => $variable->plainName === $name) ?? throw new LogicException;
		$expression = expression($replacement);
		Assert::exception(fn() => $variable->checkReplaceWithExpression($expression), InvalidArgumentException::class, $message);
		Assert::exception(fn() => $variable->replaceWithExpression($expression), InvalidArgumentException::class, $message);
		Assert::same($code, (string) $file);
	}

	$file = (new Parser)->parse('<?php "$a[$i] {$o->$m}";');
	foreach (['i' => '$x + 1', 'a' => '$x->y', 'm' => '$x . "y"'] as $name => $replacement) {
		$variable = $file->findFirst(VariableNode::class, fn(VariableNode $variable) => $variable->plainName === $name) ?? throw new LogicException;
		$expression = expression($replacement);
		$revision = $file->revision;
		$variable->checkReplaceWithExpression($expression);
		Assert::same($revision, $file->revision);
		$variable->replaceWithExpression($expression);
	}

	Assert::same('<?php "{$x->y[$x + 1]} {$o->{$x . "y"}}";', (string) $file);
});


test('the trivia an expression brings into a string are marked as standing there', function () {
	$file = (new Parser)->parse('<?php "{$a}";');
	($file->findFirst(VariableNode::class) ?? throw new LogicException)->replaceWithExpression(expression('$b->c( 1 )'));
	$trivia = array_merge(...array_map(fn(Token $token) => [...$token->leadingTrivia, ...$token->trailingTrivia], $file->getTokens()));
	Assert::same(['<?php ' => false, ' ' => true], array_column(array_map(fn(Trivia $trivia) => [$trivia->text, $trivia->inInterpolation], $trivia), 1, 0));
});


test('the trivia an expression brings out of a string are no longer marked, those of a string it holds stay', function () {
	$file = (new Parser)->parse('<?php "{$a /* c */ ->b}"; "{$d ->e}"; $x; $y;');
	[$first, $second] = $file->find(PhpSyntax\Nodes\Expression\PropertyFetchNode::class);
	$x = $file->findFirst(VariableNode::class, fn(VariableNode $variable) => $variable->plainName === 'x') ?? throw new LogicException;
	$y = $file->findFirst(VariableNode::class, fn(VariableNode $variable) => $variable->plainName === 'y') ?? throw new LogicException;
	$x->replaceWithExpression($first->withoutEdgeTrivia());
	$y->replaceWithExpression($second->withoutEdgeTrivia());
	Assert::same('<?php "{$a /* c */ ->b}"; "{$d ->e}"; $a /* c */ ->b; $d ->e;', (string) $file);

	[, , $moved, $simple] = $file->find(PhpSyntax\Nodes\Expression\PropertyFetchNode::class);
	foreach ([$moved, $simple] as $fetch) {
		foreach ($fetch->getTokens() as $token) {
			Assert::false(array_any([...$token->leadingTrivia, ...$token->trailingTrivia], fn(Trivia $trivia) => $trivia->inInterpolation));
		}
	}

	Assert::null($moved->object->getLastToken()->getTrailingSpace());
	Assert::same(' ', $simple->object->getLastToken()->getTrailingSpace());

	$file = (new Parser)->parse('<?php $x;');
	($file->findFirst(VariableNode::class) ?? throw new LogicException)->replaceWithExpression(expression('"{$a ->b}" . $c'));
	$tokens = $file->find(PhpSyntax\Nodes\Expression\PropertyFetchNode::class)[0]->getTokens();
	Assert::true($tokens[0]->trailingTrivia[0]->inInterpolation);
});
