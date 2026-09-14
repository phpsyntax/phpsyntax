<?php declare(strict_types=1);

use PhpSyntax\{Builder, CommentPolicy, Node, Parser};
use PhpSyntax\Nodes\{ArgumentNode, FileNode, PlainNodeList, SeparatedNodeList};
use PhpSyntax\Nodes\Expression\{ArrayNode, AssignmentNode, BinaryOpNode, FunctionCallNode, MethodCallNode, ParenthesizedNode, VariableNode};
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


function parse(string $code): FileNode
{
	return (new Parser)->parse($code);
}


/** @return list<Node> */
function stmts(FileNode $file): array
{
	return $file->statements->getItems();
}


/** @return SeparatedNodeList<covariant Node> */
function emptyArguments(): SeparatedNodeList
{
	$call = (new Builder)->expression('f()');
	Assert::type(FunctionCallNode::class, $call);
	return clone $call->arguments->items;
}


/** @return list<array{?int, string, string, string}>  offset, leading trivia, text and trailing trivia of each token */
function describeTokens(FileNode $file): array
{
	return array_map(fn($token) => [
		$token->getCurrentOffset(),
		implode('', array_map(fn($trivia) => $trivia->text, $token->leadingTrivia)),
		$token->text,
		implode('', array_map(fn($trivia) => $trivia->text, $token->trailingTrivia)),
	], $file->getTokens());
}


/** Asserts that every token carries the trivia the parser gives it in the printed text. */
function assertAsReparsed(FileNode $file): void
{
	Assert::same(describeTokens(parse((string) $file)), describeTokens($file), (string) $file);
}


test('writing what a place already holds changes nothing', function () {
	$code = "<?php\nf(\$a + \$b, \$c,);\n";
	$file = parse($code);
	$binary = $file->findFirst(BinaryOpNode::class) ?? throw new LogicException;
	$call = $file->findFirst(FunctionCallNode::class) ?? throw new LogicException;
	$arguments = $call->arguments->items;
	$modifiers = parse('<?php class A { public static $x; }')->findFirst(PhpSyntax\Nodes\ModifiersNode::class, fn($modifiers) => !$modifiers->isEmpty()) ?? throw new LogicException;
	$revision = $file->revision;

	$binary->left = $binary->left;
	$binary->replaceChild($binary->operator, $binary->operator);
	$binary->replaceWith($binary);
	$arguments->replaceChild($arguments[1], $arguments[1]);
	$arguments->replaceChild($arguments->getSeparators()[0], $arguments->getSeparators()[0]);
	$arguments->setTrailingSeparator($arguments->getSeparators()[1]);
	$file->statements->replaceChild($file->statements[0], $file->statements[0]);
	$file->endOfFile = $file->endOfFile;
	$modifiers->replaceChild($modifiers->getTokens()[0], $modifiers->getTokens()[0]);

	Assert::same($code, (string) $file);
	Assert::same($revision, $file->revision);
	Assert::same($binary, $binary->left->parent);

	// the text and the trivia a token has, a name, an operator and the whitespace there already
	$code = "<?php\nif (\$a) {\n\n\t\$x = -(int) \$b . foo();\n\t\$y .= 'z';\n}\n";
	$file = parse($code);
	$x = $file->findFirst(PhpSyntax\Nodes\Expression\VariableNode::class, fn($variable) => $variable->plainName === 'x')?->getFirstToken() ?? throw new LogicException;
	$x->setText($x->text)->setLeadingTrivia($x->leadingTrivia)->setTrailingTrivia($x->trailingTrivia);
	$x->setTrailingSpace(' ')->setIndentation("\t")->setBlankLinesBefore(1, "\n")->ensureStartsLine("\n");
	PhpSyntax\Indentation::set($x, "\t", "\t");
	foreach ($file->find(PhpSyntax\Nodes\Expression\BinaryOpNode::class) as $node) {
		$node->replaceOperator($node->operator->text);
	}

	$file->findFirst(PhpSyntax\Nodes\Expression\UnaryOpNode::class)?->replaceOperator('-');
	$file->findFirst(PhpSyntax\Nodes\Expression\CastNode::class)?->replaceOperator('(int)');
	$file->findFirst(PhpSyntax\Nodes\Expression\CombinedAssignmentNode::class)?->replaceOperator('.=');
	$name = $file->findFirst(FunctionCallNode::class)?->name;
	Assert::type(PhpSyntax\Nodes\NameNode::class, $name);
	$name->text = 'foo';
	Assert::same($code, (string) $file);
	Assert::same(0, $file->revision);

	// in a node standing nowhere as well
	$detached = (new Builder)->expression('$a + $b');
	assert($detached instanceof BinaryOpNode);
	$detached->right = $detached->right;
	Assert::same('$a + $b', (string) $detached);

	// a node not standing there is still refused
	Assert::exception(fn() => $binary->replaceChild($detached->left, $detached->left), InvalidArgumentException::class);
});


test('replaceWith keeps the surrounding trivia and the parent invariant', function () {
	$file = parse("<?php\n\t\$a = 1; // one\n\t\$b = 2;\n");
	$replacement = clone parse('<?php $x = 3;')->statements[0];
	$first = $replacement->getFirstToken();
	$first->setLeadingTrivia([]);
	stmts($file)[0]->replaceWith($replacement);
	Assert::same("<?php\n\t\$x = 3; // one\n\t\$b = 2;\n", (string) $file);
	Assert::same($file->statements, $replacement->parent);
	Assert::true($file->revision > 0);
	Assert::same(2, $replacement->getStartLine());
	Assert::exception(fn() => $replacement->replaceWith(stmts($file)[1]), LogicException::class, 'The node already belongs to a tree; a copy comes from `withoutEdgeTrivia()`, or from `clone` with the trivia on its edges.');
});


test('replaceWith a node without tokens gives the trivia around the old one to its neighbors', function () {
	$check = function (string $code, Closure $find, string $expected): void {
		$file = parse($code);
		$old = $find($file);
		Assert::type(Node::class, $old);
		$old->replaceWith($old instanceof SeparatedNodeList ? emptyArguments() : clone parse('')->statements);
		Assert::same($expected, (string) $file);
		// the trivia stand where the lexer puts them, and the index knows the offsets
		Assert::same(describeTokens(parse($expected)), describeTokens($file));
	};

	$statements = fn(FileNode $file) => $file->statements;
	$block = fn(FileNode $file) => $file->findFirst(PhpSyntax\Nodes\Statement\BlockNode::class)?->statements;
	$check("<?php /* keep */ foo();\n", $statements, "<?php /* keep */ \n");
	$check("<?php\r\n/** doc */\r\nfunction f() {}\r\n", $statements, "<?php\r\n/** doc */\r\n\r\n");
	$check("<?php\nif (\$a) {\n\tfoo(); // c\n}\n", $block, "<?php\nif (\$a) {\n\t // c\n}\n");
	$check("<?php { g(); /* c */ } h();\n", $block, "<?php {  /* c */ } h();\n");
	$check("<?php f( \$a, \$b );\n", fn(FileNode $file) => $file->findFirst(FunctionCallNode::class)?->arguments->items, "<?php f(  );\n");
});


test('replaceWith a node without tokens refuses where no token is left to take the trivia', function () {
	$type = (new Builder)->type('A|B');
	Assert::type(PhpSyntax\Nodes\Type\UnionTypeNode::class, $type);
	$types = $type->types;
	$types->setEdgeTrivia([new PhpSyntax\Trivia(PhpSyntax\Trivia::Comment, '/* c */')], []);
	Assert::exception(
		fn() => $types->replaceWith(emptyArguments()),
		LogicException::class,
		'The trivia around the node have no token to stay with once a node without tokens takes its place.',
	);
	Assert::same($type, $types->parent);
	Assert::same('/* c */A|B', (string) $type);
});


test('withoutEdgeTrivia() copies a node for another place', function () {
	$file = parse("<?php\n// note\n\$a = f( \$b /* in */ ); // tail\n");
	$stmt = stmts($file)[0];
	$copy = $stmt->withoutEdgeTrivia();
	Assert::same('$a = f( $b /* in */ );', (string) $copy);
	Assert::null($copy->parent);
	Assert::type($stmt::class, $copy);
	Assert::same("<?php\n// note\n\$a = f( \$b /* in */ ); // tail\n", (string) $file);
});


test('replaceWith keeps apart the tokens that would be read together', function () {
	$replace = function (string $code, string $find, string $with): string {
		$file = parse("<?php\n$code\n");
		$node = $file->findFirst(PhpSyntax\Nodes\ExpressionNode::class, fn($node) => $node->text === $find);
		Assert::type(PhpSyntax\Nodes\ExpressionNode::class, $node);
		$node->replaceWith((new Builder)->expression($with));
		Assert::same((string) $file, (string) parse((string) $file));
		return substr((string) $file, 6, -1);
	};

	Assert::same("\$x = 'a'. 119 + 1;", $replace("\$x = 'a'.f();", 'f()', '119 + 1')); // .119 is a number
	Assert::same("\$x = 4 .'s';", $replace("\$x = f().'s';", 'f()', '4')); // and so is 4.
	Assert::same('return FOO;', $replace('return(1);', '(1)', 'FOO'));
	Assert::same('$x = - -$a;', $replace('$x = -f();', 'f()', '-$a'));

	// what reads the same either way stays as it was
	Assert::same("\$x = 'a'.\$b;", $replace("\$x = 'a'.f();", 'f()', '$b'));
	Assert::same('$x = !$b;', $replace('$x = !f();', 'f()', '$b'));
	Assert::same('$x = "a{$b}c";', $replace('$x = "a{$a}c";', '$a', '$b'));
	Assert::same('$x = "a{$b[1]}";', $replace('$x = "a{$b[0]}";', '0', '1'));

	// in a tree without a file too
	$negation = (new Builder)->expression('-0');
	Assert::type(PhpSyntax\Nodes\Expression\UnaryOpNode::class, $negation);
	$negation->expression->replaceWith((new Builder)->expression('-$a'));
	Assert::same('- -$a', (string) $negation);
});


test('replaceWithExpression keeps the parentheses the place asks for', function () {
	$replace = function (string $code, string $find, string $with): string {
		$file = parse("<?php\n$code\n");
		$node = $file->findFirst(PhpSyntax\Nodes\ExpressionNode::class, fn($node) => $node->text === $find);
		Assert::type(PhpSyntax\Nodes\ExpressionNode::class, $node);
		$node->replaceWithExpression((new Builder)->expression($with));
		Assert::same((string) $file, (string) parse((string) $file));
		return substr((string) $file, 6, -1);
	};

	// an operator looser than the place
	Assert::same('$x = ($a ?? $b) . "x";', $replace('$x = f() . "x";', 'f()', '$a ?? $b'));
	Assert::same('$x = $a ?? $b;', $replace('$x = f();', 'f()', '$a ?? $b'));
	Assert::same('g($a ?? $b, 1);', $replace('g(f(), 1);', 'f()', '$a ?? $b'));
	Assert::same('$x = $a * $b + 1;', $replace('$x = f() + 1;', 'f()', '$a * $b'));
	Assert::same('$x = ($a + $b) * 2;', $replace('$x = f() * 2;', 'f()', '$a + $b'));
	Assert::same('$x = 1 - ($a - $b);', $replace('$x = 1 - f();', 'f()', '$a - $b'));
	Assert::same('$x = $a - $b - 1;', $replace('$x = f() - 1;', 'f()', '$a - $b'));
	Assert::same('$x = (bool) !$a;', $replace('$x = (bool) f();', 'f()', '!$a'));
	Assert::same('$x = ($a = 1) ? 2 : 3;', $replace('$x = f() ? 2 : 3;', 'f()', '$a = 1'));

	// what is reached into
	Assert::same('(new Foo)->bar();', $replace('f()->bar();', 'f()', 'new Foo'));
	Assert::same('$a->b->bar();', $replace('f()->bar();', 'f()', '$a->b'));
	Assert::same('($a ?: $b)[0];', $replace('f()[0];', 'f()', '$a ?: $b'));
	Assert::same('($a->b)();', $replace('f()();', 'f()', '$a->b'));

	// parentheses given are kept, and ones around the place make no second pair
	Assert::same('$x = ($a);', $replace('$x = f();', 'f()', '($a)'));
	Assert::same('$x = ($a ?? $b) . "x";', $replace('$x = (f()) . "x";', 'f()', '$a ?? $b'));
});


test('replaceWithExpression keeps the trivia around the replaced node', function () {
	$file = parse("<?php\n\$x = /* a */ f() /* b */ . 'x'; // c\n");
	$call = $file->findFirst(FunctionCallNode::class);
	Assert::type(FunctionCallNode::class, $call);
	$call->replaceWithExpression((new Builder)->expression('$a ?? $b'));
	Assert::same("<?php\n\$x = /* a */ (\$a ?? \$b) /* b */ . 'x'; // c\n", (string) $file);

	$file = parse("<?php\n\$x = /* a */ f() /* b */; // c\n");
	$call = $file->findFirst(FunctionCallNode::class);
	Assert::type(FunctionCallNode::class, $call);
	$call->replaceWithExpression((new Builder)->expression('$a ?? $b'));
	Assert::same("<?php\n\$x = /* a */ \$a ?? \$b /* b */; // c\n", (string) $file);

	// the trivia the expression brings on its own edges stand outside the parentheses
	$file = parse("<?php\n\$x = f() . 'x';\n");
	$call = $file->findFirst(FunctionCallNode::class);
	$own = parse("<?php\ng(\$a ?? \$b /* own */);")->findFirst(BinaryOpNode::class);
	Assert::type(FunctionCallNode::class, $call);
	Assert::type(BinaryOpNode::class, $own);
	$call->replaceWithExpression(clone $own);
	Assert::same("<?php\n\$x = (\$a ?? \$b) /* own */ . 'x';\n", (string) $file);
});


test('replaceWithExpression into a slot that takes no parentheses replaces bare or refuses with nothing moved', function () {
	// a parameter takes a variable, never parentheses, so another variable goes there bare
	$file = parse('<?php function f($x) {}');
	$variable = $file->findFirst(VariableNode::class) ?? throw new LogicException;
	$donor = (new Builder)->expression('$a + $b');
	assert($donor instanceof BinaryOpNode);
	$variable->replaceWithExpression($donor->left->setEdgeTrivia([], []));
	Assert::same('<?php function f($a) {}', (string) $file);

	// an expression the slot cannot take is refused before the donor loses it
	$variable = $file->findFirst(VariableNode::class);
	$donor = (new Builder)->expression('f() + $b');
	assert($donor instanceof BinaryOpNode);
	$call = $donor->left;
	$message = '`PhpSyntax\Nodes\Expression\FunctionCallNode` cannot be placed in the slot `variable` of `PhpSyntax\Nodes\ParameterNode`.';
	Assert::exception(fn() => $variable->checkReplaceWithExpression($call), InvalidArgumentException::class, $message);
	Assert::exception(fn() => $variable->replaceWithExpression($call), InvalidArgumentException::class, $message);
	Assert::same($donor, $call->parent);
	Assert::same('f() + $b', (string) $donor);
	Assert::same('<?php function f($a) {}', (string) $file);

	$variable->replaceWithExpression($variable);
	Assert::same('<?php function f($a) {}', (string) $file);
});


test('replaceWithExpression writes the name of a member or of a variable in braces unless it is a variable', function () {
	$builder = new Builder;
	foreach ([
		'$o->$m;' => ['$o->{$a . "b"};', '$o->$x;'],
		'$o->$m();' => ['$o->{$a . "b"}();', '$o->$x();'],
		'A::$m();' => ['A::{$a . "b"}();', 'A::$x();'],
		'A::$$m;' => ['A::${$a . "b"};', 'A::$$x;'],
		'$$m;' => ['${$a . "b"};', '$$x;'],
	] as $code => [$braced, $bare]) {
		foreach ([$braced => '$a . "b"', $bare => '$x'] as $expected => $with) {
			$file = parse("<?php $code");
			$name = $file->find(VariableNode::class, fn(VariableNode $node) => $node->plainName === 'm')[0] ?? throw new LogicException;
			$name->replaceWithExpression($builder->expression($with));
			Assert::same("<?php $expected", (string) $file);
			Assert::true($file->statements[0]->matches(parse("<?php $expected")->statements[0]));
		}
	}

	// a name already in braces keeps them
	$file = parse('<?php $o->{$m}();');
	($file->find(VariableNode::class, fn(VariableNode $node) => $node->plainName === 'm')[0] ?? throw new LogicException)->replaceWithExpression($builder->expression('$x'));
	Assert::same('<?php $o->{$x}();', (string) $file);
});


test('replaceWithExpression takes the expression out of the node it replaces, as replaceWith() does', function () {
	$file = parse("<?php\n\n\$s = f(\$a ?? \$b) . 'x';\n");
	$file->getIndex(); // the index is built first, so that the lift has to keep it right
	$call = $file->findFirst(FunctionCallNode::class);
	Assert::type(FunctionCallNode::class, $call);
	$argument = $call->findFirst(BinaryOpNode::class);
	Assert::type(BinaryOpNode::class, $argument);
	$call->replaceWithExpression($argument);
	Assert::same("<?php\n\n\$s = (\$a ?? \$b) . 'x';\n", (string) $file);
	Assert::same($argument, $file->findFirst(BinaryOpNode::class, fn(BinaryOpNode $node) => $node->operator->text === '??'));
	Assert::same(3, $argument->getStartLine());
	Assert::same([13, 21], $file->getIndex()->getOffsetRange($argument));
	Assert::same(
		['$s', '=', '(', '$a', '??', '$b', ')', '.', "'x'", ';', ''],
		array_map(fn(PhpSyntax\Token $token) => $token->text, $file->getIndex()->getTokens()),
	);

	$file = parse("<?php\n\$s = (\$a ?? \$b) . 'x';\n");
	$paren = $file->findFirst(ParenthesizedNode::class);
	Assert::type(ParenthesizedNode::class, $paren);
	$paren->replaceWithExpression($paren->expression);
	Assert::same("<?php\n\$s = (\$a ?? \$b) . 'x';\n", (string) $file);

	// a node standing elsewhere in a live tree is still refused, before anything moves
	$file = parse("<?php\n\$s = f() . g(\$a ?? \$b);\n");
	$calls = $file->find(FunctionCallNode::class);
	$argument = $calls[1]->findFirst(BinaryOpNode::class);
	Assert::type(BinaryOpNode::class, $argument);
	Assert::exception(
		fn() => $calls[0]->replaceWithExpression($argument),
		LogicException::class,
		'The node already belongs to a tree; %a%',
	);
	Assert::same("<?php\n\$s = f() . g(\$a ?? \$b);\n", (string) $file);
});


test('a node is lifted out of the one it replaces, and only out of that one', function () {
	$file = parse("<?php\n\$a = (\$b + 1);\n\$c = \$c + \$d;\n\nreturn \$c;\n");
	$file->getIndex(); // the index is built first, so that the lift has to keep it right

	// replaceWith(), where the parentheses go and what they held stays
	$parenthesized = $file->find(ParenthesizedNode::class)[0];
	$inner = $parenthesized->expression;
	$parenthesized->replaceWith($inner);
	Assert::same("<?php\n\$a = \$b + 1;\n\$c = \$c + \$d;\n\nreturn \$c;\n", (string) $file);
	Assert::type(AssignmentNode::class, $inner->parent);

	// the same through a slot, where the right operand takes the place of the whole expression
	$assign = $file->find(AssignmentNode::class)[1];
	$binary = $assign->expression;
	Assert::type(BinaryOpNode::class, $binary);
	$assign->expression = $binary->right;
	Assert::same("<?php\n\$a = \$b + 1;\n\$c = \$d;\n\nreturn \$c;\n", (string) $file);

	// the index followed both, so the lines and the order of the tokens are right
	Assert::same(5, stmts($file)[2]->getStartLine());
	Assert::same(
		['$a', '=', '$b', '+', '1', ';', '$c', '=', '$d', ';', 'return', '$c', ';', ''],
		array_map(fn(PhpSyntax\Token $token) => $token->text, $file->getIndex()->getTokens()),
	);

	// a node from elsewhere in the tree is still refused: nothing releases it
	Assert::exception(
		fn() => $assign->expression = $file->find(AssignmentNode::class)[0]->expression,
		LogicException::class,
		'The node already belongs to a tree; a copy comes from `withoutEdgeTrivia()`, or from `clone` with the trivia on its edges.',
	);

	// and so it is by an empty slot of a node standing nowhere, which is written the way a new node is built
	$yield = (new Builder)->expression('yield');
	Assert::type(PhpSyntax\Nodes\Expression\YieldNode::class, $yield);
	Assert::exception(
		fn() => $yield->value = $assign->expression,
		LogicException::class,
		'The node already belongs to a tree; %a%',
	);
	Assert::null($yield->value);
	Assert::same($assign, $assign->expression->parent);
});


test('a node is taken out of a subtree without a file, which nothing indexes', function () {
	$builder = new Builder;
	$file = parse("<?php\n\$rows = db_query(\$db, 'SELECT 1');\n");
	$file->getIndex();

	// the codemod written in the order it is thought: replace the call, then take what it held
	$call = $file->find(FunctionCallNode::class)[0];
	$connection = $call->arguments->findArgument('connection', 0);
	$query = $call->arguments->findArgument('query', 1);
	$replacement = $builder->expression('$db->query($sql)');
	Assert::type(ArgumentNode::class, $connection);
	Assert::type(ArgumentNode::class, $query);
	Assert::type(MethodCallNode::class, $replacement);
	$call->replaceWith($replacement);
	$replacement->object = $connection->value;
	$argument = $replacement->arguments->items[0];
	Assert::type(ArgumentNode::class, $argument);
	$argument->value = $query->value;
	Assert::same("<?php\n\$rows = \$db->query('SELECT 1');\n", (string) $file);
	Assert::same(3, $file->endOfFile->getCurrentLine());

	// a piece of a fragment goes the same way
	$fragment = $builder->statement('g($a);');
	$statement = $builder->statement('f(1);');
	$target = $statement->findFirst(ArgumentNode::class);
	$source = $fragment->findFirst(ArgumentNode::class);
	Assert::type(ArgumentNode::class, $target);
	Assert::type(ArgumentNode::class, $source);
	$target->value = $source->value;
	Assert::same('f($a);', (string) $statement);

	// a child of the node doing the write is not taken from anywhere: it would be listed twice
	$list = new PlainNodeList([$first = $builder->statement('f();'), $builder->statement('g();')]);
	Assert::exception(
		fn() => $list->append($first),
		LogicException::class,
		'The node already belongs to a tree; a copy comes from `withoutEdgeTrivia()`, or from `clone` with the trivia on its edges.',
	);
});


test('remove takes a whole line, keeps blank lines and the open tag', function () {
	$file = parse("<?php\n\n\$a;\n\n\t\$b;\n\$c;\n");
	stmts($file)[0]->remove();
	Assert::same("<?php\n\n\n\t\$b;\n\$c;\n", (string) $file);
	stmts($file)[0]->remove();
	Assert::same("<?php\n\n\n\$c;\n", (string) $file);
	stmts($file)[0]->remove();
	Assert::same("<?php\n\n\n", (string) $file);
	Assert::count(0, stmts($file));
	Assert::true($file->revision >= 3);
});


test('a statement ended by a close tag leaves the tag, so the text after it stays text', function () {
	$remove = function (string $code, int $index): string {
		$file = parse($code);
		stmts($file)[$index]->remove();
		$output = (string) $file;
		parse($output); // still code where it was code and text where it was text
		return $output;
	};

	Assert::same("<?php ?>\n<p>text</p>\n", $remove("<?php \$form->render() ?>\n<p>text</p>\n", 0));
	Assert::same("<?php\nfoo();\n?>\n<b>x</b>\n", $remove("<?php\nfoo();\nbar() ?>\n<b>x</b>\n", 1));
	Assert::same('<?php foo(); /* c */ ?>tail', $remove('<?php foo(); /* c */ bar() ?>tail', 1));
	Assert::same("<?php\n\tfoo();\n\t?>\nhtml", $remove("<?php\n\tfoo();\n\tbar()?>\nhtml", 1));
	Assert::same("<?php\n\tfoo();\n\t?>html", $remove("<?php\n\tfoo();\n\tbar() ?>html", 1));
	Assert::same('x<?php if ($a): ?>y<?php ?>z<?php endif ?>', (string) (function () {
		$file = parse('x<?php if ($a): ?>y<?php echo 1 ?>z<?php endif ?>');
		$file->findFirst(PhpSyntax\Nodes\Statement\EchoNode::class)?->remove();
		return $file;
	})());

	// a statement that opens its code too goes whole, and so does the empty statement of a close tag
	Assert::same('xy', $remove('x<?= $a ?>y', 1));
	Assert::same('<?php foo(); ', $remove("<?php foo(); ?>\n", 1));
});


test('a statement next to inline HTML is removed with the text outside the code as it was', function () {
	$html = fn(string $code): string => (string) preg_replace('~<\?php\s.*?(?:\?>\n?|$)~s', '', $code);
	$cases = [
		["<?php 1; ?>\nhtml\n<?php 2; ?>\nmore", 3, CommentPolicy::MoveToNextToken],
		["<?php 1; ?>\nhtml\n<?php /* c */ 2; ?>\nmore", 3, CommentPolicy::MoveToNextToken],
		["<?php 1; ?>\nhtml\n<?php\n\t// c\n\t2; ?>\nmore", 3, CommentPolicy::MoveToNextToken],
		["<?php 1; ?>\nhtml\n<?php\n\t// c\n\t2; ?>\nmore", 3, CommentPolicy::MoveToPreviousToken],
		["x\n<?php // c\n1;\n?>\nhtml", 1, CommentPolicy::MoveToPreviousToken],
	];
	foreach ($cases as [$code, $index, $policy]) {
		$file = parse($code);
		stmts($file)[$index]->remove($policy);
		Assert::same($html($code), $html((string) $file), (string) $file);
	}

	$file = parse("x\n<?php // c\n1;\n?>\nhtml");
	stmts($file)[1]->remove(CommentPolicy::MoveToPreviousToken);
	Assert::same("x\n<?php // c\n?>\nhtml", (string) $file);
});


test('an item of a list goes with the separator that goes with it', function () {
	$remove = function (string $code, int $index): string {
		$file = parse($code);
		$list = $file->findFirst(PhpSyntax\Nodes\SeparatedNodeList::class);
		$list?->getItems()[$index]->remove();
		return (string) $file;
	};

	// the last item of a list written on several lines takes its line, the trailing comma stays behind
	$multiline = "<?php\n\$c = [\n\t'a' => 1,\n\t'b' => 2,\n\t'c' => 3,\n];\n";
	Assert::same("<?php\n\$c = [\n\t'a' => 1,\n\t'b' => 2,\n];\n", $remove($multiline, 2));
	Assert::same("<?php\n\$c = [\n\t'b' => 2,\n\t'c' => 3,\n];\n", $remove($multiline, 0));
	Assert::same("<?php\n\$c = [\n\t'a' => 1,\n\t'b' => 2\n];\n", $remove("<?php\n\$c = [\n\t'a' => 1,\n\t'b' => 2,\n\t'c' => 3\n];\n", 2));

	// on one line the gap the separator opened goes with it
	Assert::same('<?php f(1, 2);', $remove('<?php f(1, 2, 3);', 2));
	Assert::same('<?php f(1, 3);', $remove('<?php f(1, 2, 3);', 1));
	Assert::same('<?php f( 1 , 3 );', $remove('<?php f( 1 , 2 , 3 );', 1));
	Assert::same('<?php f($a,);', $remove('<?php f($a, $c,);', 1));
	Assert::same('<?php f();', $remove('<?php f(1);', 0));

	// what ends the line stays, and nothing dangles before it
	Assert::same("<?php \$x = [1,\n\t3];", $remove("<?php \$x = [1, 2,\n\t3];", 1));
});


test('remove inside a line keeps the whitespace around', function () {
	$file = parse('<?php $a; $b; $c;');
	stmts($file)[1]->remove();
	Assert::same('<?php $a;  $c;', (string) $file);
	stmts($file)[0]->remove();
	Assert::same('<?php   $c;', (string) $file);
});


test('remove merging blank lines leaves the narrower gap, and none at the edge of the list', function () {
	$code = "<?php\nclass A\n{\n\tpublic \$a;\n\n\tpublic \$b;\n\n\n\t// c\n\tpublic \$c;\n\n\tpublic \$d;\n}\n";
	$members = fn(FileNode $file) => ($file->findFirst(PhpSyntax\Nodes\Statement\ClassNode::class) ?? throw new LogicException)->members;

	$file = parse($code);
	$members($file)[1]->remove();
	Assert::same("<?php\nclass A\n{\n\tpublic \$a;\n\n\n\n\t// c\n\tpublic \$c;\n\n\tpublic \$d;\n}\n", (string) $file);

	$file = parse($code);
	$members($file)[1]->remove(mergeBlankLines: true);
	Assert::same("<?php\nclass A\n{\n\tpublic \$a;\n\n\t// c\n\tpublic \$c;\n\n\tpublic \$d;\n}\n", (string) $file);

	$members($file)[0]->remove(mergeBlankLines: true);
	Assert::same("<?php\nclass A\n{\n\t// c\n\tpublic \$c;\n\n\tpublic \$d;\n}\n", (string) $file);

	$members($file)[1]->remove(mergeBlankLines: true);
	Assert::same("<?php\nclass A\n{\n\t// c\n\tpublic \$c;\n}\n", (string) $file);

	// the items of a separated list, and a line ending of the file
	$file = parse("<?php\r\n\$a = [\r\n\t1,\r\n\r\n\t2,\r\n\r\n\r\n\t3,\r\n];\r\n");
	($file->findFirst(ArrayNode::class) ?? throw new LogicException)->items[1]->remove(mergeBlankLines: true);
	Assert::same("<?php\r\n\$a = [\r\n\t1,\r\n\r\n\t3,\r\n];\r\n", (string) $file);

	// within a line there are no blank lines to merge
	$file = parse('<?php $a; $b; $c;');
	stmts($file)[1]->remove(mergeBlankLines: true);
	Assert::same('<?php $a;  $c;', (string) $file);

	// nor where the node shares its line with another one
	$file = parse("<?php\nclass A {\n\tpublic \$a; public \$b;\n\n\tpublic \$c;\n}\n");
	$members($file)[1]->remove(mergeBlankLines: true);
	Assert::same("<?php\nclass A {\n\tpublic \$a;\n\n\tpublic \$c;\n}\n", (string) $file);
});


test('a comment sharing the line of a removed node stands on a line of its own, or before the next token within a line', function () {
	$file = parse("<?php\n/* lead */ \$b;\n\$c;\n");
	stmts($file)[0]->remove();
	Assert::same("<?php\n/* lead */\n\$c;\n", (string) $file);

	$file = parse("<?php\n\t/* lead */ \$b; // b\n\t\$c;\n");
	stmts($file)[0]->remove();
	Assert::same("<?php\n\t/* lead */\n\t// b\n\t\$c;\n", (string) $file);

	$file = parse('<?php $a; $b /* x */; $c;');
	stmts($file)[1]->remove();
	Assert::same('<?php $a;  /* x */ $c;', (string) $file);
});


test('remove merging blank lines counts them above the comments the node leaves behind', function () {
	$file = parse("<?php\n\$a;\n\n/** d */\n\$b;\n\n\n\$c;\n");
	stmts($file)[1]->remove(mergeBlankLines: true);
	Assert::same("<?php\n\$a;\n\n/** d */\n\$c;\n", (string) $file);

	$file = parse("<?php\n\$a;\n\n\$b; // b\n\n\n\$c;\n");
	stmts($file)[1]->remove(mergeBlankLines: true);
	Assert::same("<?php\n\$a;\n\n// b\n\$c;\n", (string) $file);

	$file = parse("<?php\n\$a;\n\n/** d */\n\$b;\n\n\n\$c;\n");
	stmts($file)[1]->remove(CommentPolicy::MoveToPreviousToken, mergeBlankLines: true);
	Assert::same("<?php\n\$a;\n/** d */\n\n\$c;\n", (string) $file);
	assertAsReparsed($file);
	Assert::same(0, $file->statements[1]->getFirstToken()->countBlankLinesBefore());

	// the blank lines between a comment and the node stay below the comment
	$file = parse("<?php\nclass A\n{\n\t// columns\n\n\tpublic \$a;\n\n\tpublic \$b;\n}\n");
	$members = ($file->findFirst(PhpSyntax\Nodes\Statement\ClassNode::class) ?? throw new LogicException)->members;
	$members[0]->remove(mergeBlankLines: true);
	Assert::same("<?php\nclass A\n{\n\t// columns\n\n\tpublic \$b;\n}\n", (string) $file);

	$file = parse("<?php\n\$x;\n\n// columns\n\n\$a;\n\n\$b;\n");
	stmts($file)[1]->remove(mergeBlankLines: true);
	Assert::same("<?php\n\$x;\n\n// columns\n\n\$b;\n", (string) $file);

	// and so do those between two comments
	$file = parse("<?php\n\$x;\n\n// c\n\n// e\n\n\$a;\n\n\$b;\n");
	stmts($file)[1]->remove(mergeBlankLines: true);
	Assert::same("<?php\n\$x;\n\n// c\n\n// e\n\n\$b;\n", (string) $file);

	// toward the end of the list no more of them than the gap merged there
	$file = parse("<?php\nclass A {\n\tpublic \$a;\n\n\t/** c */\n\n\tpublic \$b;\n}\n");
	($file->findFirst(PhpSyntax\Nodes\Statement\ClassNode::class) ?? throw new LogicException)->members[1]->remove(mergeBlankLines: true);
	Assert::same("<?php\nclass A {\n\tpublic \$a;\n\t/** c */\n}\n", (string) $file);

	// a comment set apart by a blank line is no comment of the node, it stays with the gap above it
	$file = parse("<?php\nclass A {\n\tpublic \$a;\n\n\t// c\n\n\tpublic \$b;\n}\n");
	($file->findFirst(PhpSyntax\Nodes\Statement\ClassNode::class) ?? throw new LogicException)->members[1]->remove(mergeBlankLines: true);
	Assert::same("<?php\nclass A {\n\tpublic \$a;\n\n\t// c\n}\n", (string) $file);
});


test('remove merging blank lines keeps the gap toward the edge of the list and the line ending of the file', function () {
	$file = parse("<?php\n\n\$a;\n\n\n\$b;\n\n\$c;\n");
	stmts($file)[0]->remove(mergeBlankLines: true);
	Assert::same("<?php\n\n\$b;\n\n\$c;\n", (string) $file);

	// the open tag ends a line, but its text is no line ending to write
	$file = parse("<?php\r\n\$a;\r\n\r\n\$b;\r\n");
	stmts($file)[0]->remove(mergeBlankLines: true);
	Assert::same("<?php\r\n\$b;\r\n", (string) $file);

	$file = parse("<?php\r\n\$a;\r\n\r\n\$b;\r\n\$c;\r\n");
	stmts($file)[1]->remove(mergeBlankLines: true);
	Assert::same("<?php\r\n\$a;\r\n\$c;\r\n", (string) $file);

	// the text after a close tag is no gap to merge
	$file = parse("<?php\n\$a;\n\n\$b ?>\n\n\nx");
	stmts($file)[1]->remove(mergeBlankLines: true);
	Assert::same("<?php\n\$a;\n\n?>\n\n\nx", (string) $file);
});


test('comments of a removed node follow the policy', function () {
	$code = "<?php\n\$a;\n/** doc */\n\$b; // b\n\$c;\n";
	$file = parse($code);
	stmts($file)[1]->remove();
	Assert::same("<?php\n\$a;\n/** doc */\n// b\n\$c;\n", (string) $file);
	Assert::same('/** doc */', stmts($file)[1]->getDocComment()?->text);

	$file = parse($code);
	stmts($file)[1]->remove(CommentPolicy::MoveToPreviousToken);
	Assert::same("<?php\n\$a;\n/** doc */\n// b\n\$c;\n", (string) $file);
	assertAsReparsed($file);

	$file = parse($code);
	stmts($file)[1]->remove(CommentPolicy::StayWithNode);
	Assert::same("<?php\n\$a;\n\$c;\n", (string) $file);

	$file = parse("<?php\n\$a; /* x */ \$b; \$c;");
	stmts($file)[1]->remove(CommentPolicy::MoveToPreviousToken);
	Assert::same("<?php\n\$a; /* x */  \$c;", (string) $file);

	// a comment on a line of its own keeps the indentation of that line wherever it goes
	$indented = "<?php\nfunction f()\n{\n\t// note\n\t\$b = 1;\n\n\treturn 1;\n}\n";
	$file = parse($indented);
	$file->find(ExpressionStatementNode::class)[0]->remove(CommentPolicy::StayWithNode);
	Assert::same("<?php\nfunction f()\n{\n\n\treturn 1;\n}\n", (string) $file);

	$file = parse($indented);
	$file->find(ExpressionStatementNode::class)[0]->remove(CommentPolicy::MoveToPreviousToken);
	Assert::same("<?php\nfunction f()\n{\n\t// note\n\n\treturn 1;\n}\n", (string) $file);
});


test('a comment set apart above a node is a preamble, which stays whatever the policy', function () {
	$code = "<?php\n/** License. */\n\nclass A\n{\n\t// --- getters ---\n\n\t/** Returns a. */\n\tpublic function a() {}\n\n\t/** Returns b. */\n\tpublic function b() {}\n}\n";
	$method = fn(FileNode $file) => $file->findFirst(PhpSyntax\Nodes\Member\MethodNode::class) ?? throw new LogicException;
	$cases = [
		[CommentPolicy::MoveToNextToken, false, "{\n\t// --- getters ---\n\n\t/** Returns a. */\n\n\t/** Returns b. */"],
		[CommentPolicy::MoveToPreviousToken, false, "{\n\t/** Returns a. */\n\t// --- getters ---\n\n\n\t/** Returns b. */"],
		[CommentPolicy::StayWithNode, false, "{\n\t// --- getters ---\n\n\n\t/** Returns b. */"],
		[CommentPolicy::StayWithNode, true, "{\n\t// --- getters ---\n\n\t/** Returns b. */"],
	];
	foreach ($cases as [$policy, $merge, $expected]) {
		$file = parse($code);
		$method($file)->remove($policy, $merge);
		Assert::contains($expected, (string) $file);
		assertAsReparsed($file);
	}

	// the comments of the open tag line and of the head of a file stay as well
	$file = parse("<?php\n// License\n\n\$a;\n\n\$b;\n");
	stmts($file)[0]->remove(CommentPolicy::StayWithNode, mergeBlankLines: true);
	Assert::same("<?php\n// License\n\n\$b;\n", (string) $file);

	$file = parse("<?php // License\n\$a;\n\$b;\n");
	stmts($file)[0]->remove(CommentPolicy::StayWithNode);
	Assert::same("<?php // License\n\$b;\n", (string) $file);

	$file = parse("x\n<?php\n// pre\n\n\$a;\n\n\$b;\n");
	stmts($file)[1]->remove(CommentPolicy::StayWithNode, mergeBlankLines: true);
	Assert::same("x\n<?php\n// pre\n\n\$b;\n", (string) $file);

	// above the last item of a list, whose separator before it goes too
	$code = "<?php\n\$x = [\n\t1,\n\t// pre\n\n\t// own\n\t2\n];\n";
	$file = parse($code);
	($file->findFirst(ArrayNode::class) ?? throw new LogicException)->items[1]->remove(CommentPolicy::StayWithNode);
	Assert::same("<?php\n\$x = [\n\t1\n\t// pre\n];\n", (string) $file);

	$file = parse($code);
	($file->findFirst(ArrayNode::class) ?? throw new LogicException)->items[1]->remove();
	Assert::same("<?php\n\$x = [\n\t1\n\t// pre\n\t// own\n];\n", (string) $file);
});


test('a node owns its doc comment, the block of comments right above it and the one holding the doc comment', function () {
	$file = parse("<?php\n\$x;\n\n/** doc */\n\n\$a;\n\$b;\n");
	$node = stmts($file)[1];
	$node->remove(CommentPolicy::StayWithNode);
	Assert::same("<?php\n\$x;\n\n\n\$b;\n", (string) $file);
	Assert::same("/** doc */\n\n\$a;\n", (string) $node);

	$file = parse("<?php\n\$x;\n// section\n\n// note\n/** doc */\n\$a;\n\$b;\n");
	$node = stmts($file)[1];
	$node->remove(CommentPolicy::StayWithNode);
	Assert::same("<?php\n\$x;\n// section\n\n\$b;\n", (string) $file);
	Assert::same("// note\n/** doc */\n\$a;\n", (string) $node);

	// the doc comment of a class is the header of the file written before it, which PHP reads as its documentation
	$file = parse("<?php\n/** License. */\nclass A {}\n");
	stmts($file)[0]->remove(CommentPolicy::StayWithNode);
	Assert::same("<?php\n", (string) $file);
});


test('a node taken out with its comments moves with them, one taken out without them goes bare', function () {
	$code = "<?php\nclass A\n{\n\t// --- getters ---\n\n\t/** Returns a. */\n\tpublic function a() {} // a\n\n\tpublic function b() {}\n}\n\nclass B\n{\n\tpublic function c() {}\n}\n";
	$file = parse($code);
	[$a, $b] = $file->find(PhpSyntax\Nodes\Statement\ClassNode::class);
	$method = $a->members[0];
	$method->remove(CommentPolicy::StayWithNode, mergeBlankLines: true);
	Assert::same("\t/** Returns a. */\n\tpublic function a() {} // a\n", (string) $method);
	$b->members->append($method);
	Assert::same(
		"<?php\nclass A\n{\n\t// --- getters ---\n\n\tpublic function b() {}\n}\n\nclass B\n{\n\tpublic function c() {}\n\t/** Returns a. */\n\tpublic function a() {} // a\n}\n",
		(string) $file,
	);
	assertAsReparsed($file);

	// the comments the tree keeps are not left on the node as well, those inside it neither
	$file = parse("<?php\n\$x;\n/** doc */\n\$a = f(/* in */ 1); // end\n\$b;\n");
	$node = stmts($file)[1];
	Assert::type(ExpressionStatementNode::class, $node);
	$node->remove();
	Assert::same("<?php\n\$x;\n/** doc */\n/* in */\n// end\n\$b;\n", (string) $file);
	Assert::same('$a = f(1);', (string) $node);
	$file->statements->append($node);
	Assert::same("<?php\n\$x;\n/** doc */\n/* in */\n// end\n\$b;\n\$a = f(1);\n", (string) $file);

	// a subtree without a file has nowhere to keep the comments, so the node takes them all
	$block = (new Builder)->statement("{\n\t// c\n\t\$a;\n}");
	Assert::type(PhpSyntax\Nodes\Statement\BlockNode::class, $block);
	$statement = $block->statements[0];
	$statement->remove();
	Assert::same("\t// c\n\t\$a;\n", (string) $statement);
});


test('remove leaves every token with the trivia the parser gives it in the printed text', function () {
	$statement = fn(int $i) => fn(FileNode $file) => stmts($file)[$i];
	$item = fn(int $i) => fn(FileNode $file) => ($file->findFirst(ArrayNode::class) ?? throw new LogicException)->items[$i];
	$next = CommentPolicy::MoveToNextToken;
	$previous = CommentPolicy::MoveToPreviousToken;
	$cases = [
		// an inline comment moved after the previous item is set apart from it
		["<?php \$x = [\n\t1,\n\t2 // two\n];\n", $item(1), $previous, "<?php \$x = [\n\t1 // two\n];\n"],
		["<?php \$x = [\n\t1,\n\t2 // two\n];\n", $item(1), $next, "<?php \$x = [\n\t1 // two\n];\n"],
		// a comment on a line of its own before a separator keeps the indentation of its line, the next item takes the one of the node
		["<?php \$x = [\n\t1\n\t// c\n\t, 2,\n];\n", $item(0), $next, "<?php \$x = [\n\t// c\n\t2,\n];\n"],
		["<?php \$x = [\n\t1\n\t// c\n\t, 2,\n];\n", $item(0), $previous, "<?php \$x = [\n\t// c\n\t2,\n];\n"],
		// the space before a comment goes with it
		['<?php $a; /* c */ $b;', $statement(0), $next, '<?php /* c */ $b;'],
		["<?php\n\$x;\n\$a; /* c */ \$b;\n", $statement(1), $previous, "<?php\n\$x;\n/* c */ \$b;\n"],
		// the comment close to the previous token with the gap below it, or close to the next one with the gap above it
		["<?php\n\$x;\n\n\$a; // c\n\n\$b;\n", $statement(1), $previous, "<?php\n\$x;\n// c\n\n\n\$b;\n"],
		["<?php\n\$x;\n\n\$a; // c\n\n\$b;\n", $statement(1), $next, "<?php\n\$x;\n\n// c\n\n\$b;\n"],
		["<?php\n\$x;\n\$a /* in */;\n\$b;\n", $statement(1), $previous, "<?php\n\$x;\n/* in */\n\$b;\n"],
		['<?php $a; $b /* x */; $c;', $statement(1), $next, '<?php $a;  /* x */ $c;'],
		[
			"<?php\nif (1) {\n\t\$x;\n\t\$a; // c\n\t\$b;\n}\n",
			fn(FileNode $file) => $file->find(ExpressionStatementNode::class)[1],
			$previous,
			"<?php\nif (1) {\n\t\$x;\n\t// c\n\t\$b;\n}\n",
		],
	];
	foreach ($cases as [$code, $node, $policy, $expected]) {
		$file = parse($code);
		$node($file)->remove($policy);
		Assert::same($expected, (string) $file);
		assertAsReparsed($file);
	}
});


test('a comment that ended the line of a removed node keeps its indentation on the line it gets', function () {
	$code = "<?php\n\$config = [\n\t'driver' => 1,\n\t'host' => 2, // the host\n\t'port' => 3,\n];\n";
	$expected = "<?php\n\$config = [\n\t'driver' => 1,\n\t// the host\n\t'port' => 3,\n];\n";
	foreach ([CommentPolicy::MoveToNextToken, CommentPolicy::MoveToPreviousToken] as $policy) {
		$file = parse($code);
		$array = $file->findFirst(ArrayNode::class) ?? throw new LogicException('No array.');
		$array->items[1]->remove($policy);
		Assert::same($expected, (string) $file);
	}

	$file = parse("<?php\nfunction f()\n{\n\t\$a = 1; // note\n\t\$b = 2;\n}\n");
	$file->find(ExpressionStatementNode::class)[0]->remove();
	Assert::same("<?php\nfunction f()\n{\n\t// note\n\t\$b = 2;\n}\n", (string) $file);
});


test('a refused write leaves the tree as it stood', function () {
	// the type of the slot decides before the trivia around the node move
	$file = parse('<?php /* keep */ $a;');
	$stmt = stmts($file)[0];
	Assert::type(ExpressionStatementNode::class, $stmt);
	Assert::exception(
		fn() => $stmt->expression->replaceWith((new Builder)->statement('return;')),
		InvalidArgumentException::class,
		'`PhpSyntax\\Nodes\\Statement\\ReturnNode` cannot be placed in the slot `expression` of `PhpSyntax\\Nodes\\Statement\\ExpressionStatementNode`.',
	);
	Assert::same('<?php /* keep */ $a;', (string) $file);

	// a sibling is refused before the item it would replace is released, with the index built and without
	foreach ([true, false] as $indexed) {
		$file = parse('<?php f($a, $b);');
		if ($indexed) {
			$file->getIndex();
		}

		$call = $file->findFirst(FunctionCallNode::class);
		Assert::type(FunctionCallNode::class, $call);
		$items = $call->arguments->items;
		[$first, $second] = $items->getItems();
		Assert::exception(
			fn() => $items->replaceChild($first, $second),
			LogicException::class,
			'The node already belongs to a tree; a copy comes from `withoutEdgeTrivia()`, or from `clone` with the trivia on its edges.',
		);
		Assert::same($items, $first->parent);
		Assert::same([$first, $second], $items->getItems());
		Assert::same('<?php f($a, $b);', (string) $file);
	}

	// an item already in the list is refused before it takes the line ending of its neighbor
	$file = parse("<?php\n\$a;\n\$b;\n");
	Assert::exception(
		fn() => $file->statements->append($file->statements[1]),
		LogicException::class,
		'The node already belongs to a tree; a copy comes from `withoutEdgeTrivia()`, or from `clone` with the trivia on its edges.',
	);
	Assert::same("<?php\n\$a;\n\$b;\n", (string) $file);
});


test('an insertion of an item and a separator checks both before either moves', function () {
	$builder = new Builder;
	$file = parse('<?php f($a, $b);');
	$call = $file->findFirst(FunctionCallNode::class);
	Assert::type(FunctionCallNode::class, $call);
	$items = $call->arguments->items;

	// the separator stands in the list already, so the item stays in the fragment it came from
	$fragment = $builder->expression('g($c)');
	Assert::type(FunctionCallNode::class, $fragment);
	$item = $fragment->arguments->items[0];
	Assert::exception(
		fn() => $items->append($item, $items->getSeparators()[0]),
		LogicException::class,
		'The token already belongs to a tree; a copy comes from `clone`.',
	);
	Assert::same($fragment->arguments->items, $item->parent);
	Assert::same('g($c)', (string) $fragment);

	// a separator taken out of the item itself would stand in two lists at once
	$fragment = $builder->expression('g([$c, $d])');
	Assert::type(FunctionCallNode::class, $fragment);
	$item = $fragment->arguments->items[0];
	Assert::type(ArgumentNode::class, $item);
	Assert::type(PhpSyntax\Nodes\Expression\ArrayNode::class, $item->value);
	$inner = $item->value->items;
	$separator = $inner->getSeparators()[0];
	Assert::exception(
		fn() => $items->append($item, $separator),
		LogicException::class,
		'The separator cannot be a part of the item it separates.',
	);
	Assert::same($fragment->arguments->items, $item->parent);
	Assert::same($inner, $separator->parent);
	Assert::same('g([$c, $d])', (string) $fragment);

	Assert::same('<?php f($a, $b);', (string) $file);
	Assert::same($file->getTokens(), $file->getIndex()->getTokens());
});


test('a node cannot be placed inside itself', function () {
	$node = (new Builder)->expression('($a)');
	Assert::type(ParenthesizedNode::class, $node);
	Assert::exception(
		fn() => $node->expression = $node,
		LogicException::class,
		'A node cannot be placed inside itself or inside what it holds.',
	);
	Assert::same('($a)', (string) $node);
	Assert::null($node->parent);

	// not even into an empty slot of a node standing nowhere, which is written the way a new node is built
	$yield = (new Builder)->expression('yield');
	Assert::type(PhpSyntax\Nodes\Expression\YieldNode::class, $yield);
	Assert::exception(
		fn() => $yield->value = $yield,
		LogicException::class,
		'A node cannot be placed inside itself or inside what it holds.',
	);
	Assert::null($yield->value);

	// nor into a list standing nowhere, which the parser fills the same way
	$list = new PlainNodeList([]);
	Assert::exception(
		fn() => $list->append($list),
		LogicException::class,
		'A node cannot be placed inside itself or inside what it holds.',
	);
	Assert::count(0, $list);

	$list = new SeparatedNodeList([]);
	Assert::exception(
		fn() => $list->append($list),
		LogicException::class,
		'A node cannot be placed inside itself or inside what it holds.',
	);
	Assert::count(0, $list);

	// nor may what holds it be written below it
	$file = parse('<?php f($a);');
	$call = $file->findFirst(FunctionCallNode::class);
	Assert::type(FunctionCallNode::class, $call);
	$argument = $call->arguments->items[0];
	Assert::type(ArgumentNode::class, $argument);
	Assert::exception(
		fn() => $argument->value = $call,
		LogicException::class,
		'A node cannot be placed inside itself or inside what it holds.',
	);
	Assert::same('<?php f($a);', (string) $file);

	// not even the root of a fragment, which stands nowhere itself
	$call = (new Builder)->expression('f($a)');
	Assert::type(FunctionCallNode::class, $call);
	$argument = $call->arguments->items[0];
	Assert::type(ArgumentNode::class, $argument);
	Assert::exception(
		fn() => $argument->value = $call,
		LogicException::class,
		'A node cannot be placed inside itself or inside what it holds.',
	);
	Assert::same('f($a)', (string) $call);
	Assert::null($call->parent);
});


test('a child that does not fit the slot is refused', function () {
	$node = (new Builder)->expression('($a)');
	Assert::type(ParenthesizedNode::class, $node);
	Assert::exception(
		fn() => $node->replaceChild($node->expression, new PhpSyntax\Token(1, 'x')),
		InvalidArgumentException::class,
		'Token `x` cannot be placed in the slot `expression` of `PhpSyntax\\Nodes\\Expression\\ParenthesizedNode`.',
	);
	Assert::same('($a)', (string) $node); // a refused write leaves the node as it was
});


test('remove is only for list items', function () {
	$stmt = stmts(parse('<?php $a;'))[0];
	Assert::type(ExpressionStatementNode::class, $stmt);
	Assert::exception($stmt->expression->remove(...), LogicException::class, 'Only an item of a list can be removed; a slot is emptied by its setter.');
	Assert::exception(fn() => new VariableNode(null, null, new PhpSyntax\Token(1, 'x'), null)->replaceWith($stmt->expression), LogicException::class, 'A node without a parent cannot be replaced.');
});
