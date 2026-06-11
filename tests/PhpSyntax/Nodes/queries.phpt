<?php declare(strict_types=1);

use PhpSyntax\{Associativity, Builder, DereferenceKind, Parser, Trivia};
use PhpSyntax\Nodes\Statement\{ExpressionStatementNode, InlineHtmlNode};
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


function parseStatement(string $code): ExpressionStatementNode
{
	$stmt = (new Parser)->parse("<?php\n$code")->statements[0];
	assert($stmt instanceof ExpressionStatementNode);
	return $stmt;
}


test('$text is the node without the trivia on its edges, getTokens() the tokens under it', function () {
	$file = (new Parser)->parse("<?php\n\n// note\n\$a = f( 1, /* x */ 2 ); // tail\n");
	$stmt = $file->statements[0];
	Assert::same('$a = f( 1, /* x */ 2 );', $stmt->text);
	Assert::same("<?php\n\n// note\n\$a = f( 1, /* x */ 2 ); // tail\n", (string) $file);
	// the file is a node with edges too, and the open tag is the leading trivia of its first token
	Assert::same("\$a = f( 1, /* x */ 2 ); // tail\n", $file->text);
	Assert::same(['$a', '=', 'f', '(', '1', ',', '2', ')', ';', ''], array_map(fn($token) => $token->text, $file->getTokens()));

	$empty = (new PhpSyntax\Nodes\PlainNodeList);
	Assert::same('', $empty->text);
	Assert::same([], $empty->getTokens());

	// a heredoc keeps the text of its body, whitespace and all
	$heredoc = (new Builder)->expression("<<<EOT\n\tx\n\tEOT");
	Assert::same("<<<EOT\n\tx\n\tEOT", $heredoc->text);
});


test('the trivia on the edges of a node are read where setEdgeTrivia() writes them', function () {
	$file = (new Parser)->parse("<?php\n\n// note\n\$a = 1; // tail\n");
	$stmt = $file->statements[0];
	Assert::same(["<?php\n", "\n", '// note', "\n"], array_map(fn($trivia) => $trivia->text, $stmt->leadingTrivia));
	Assert::same([' ', '// tail', "\n"], array_map(fn($trivia) => $trivia->text, $stmt->trailingTrivia));
	Assert::same($stmt->getFirstToken()->leadingTrivia, $stmt->leadingTrivia);

	$stmt->setEdgeTrivia([], []);
	Assert::same([], $stmt->leadingTrivia);
	Assert::same([], $stmt->trailingTrivia);

	$empty = (new PhpSyntax\Nodes\PlainNodeList);
	Assert::same([], $empty->leadingTrivia);
	Assert::same([], $empty->trailingTrivia);
});


test('hasInnerComment() sees comments inside the node, not on its edges', function () {
	Assert::false(parseStatement("/* a */ f(1); // b\n")->hasInnerComment());
	Assert::true(parseStatement("f(/* a */ 1);\n")->hasInnerComment());
	Assert::true(parseStatement("f(\n\t1, // a\n);\n")->hasInnerComment());
	Assert::true(parseStatement("f(1) /* a */;\n")->hasInnerComment());
	Assert::false(parseStatement("f(1)\n\t;\n")->hasInnerComment());

	// the node walks its own tokens, so a subtree taken out of the tree answers as it did inside it
	$detached = clone parseStatement("f(/* a */ 1);\n");
	Assert::true($detached->hasInnerComment());
	Assert::same(['/* a */'], array_map(fn($trivia) => $trivia->text, $detached->getInnerComments()));
	Assert::true((new Builder)->expression('f(/* a */ 1)')->hasInnerComment());
});


test('find() takes a class and a predicate, findFirst() stops at the first match', function () {
	$file = (new Parser)->parse('<?php f(1, 2); g(3);');
	$calls = $file->find(PhpSyntax\Nodes\Expression\FunctionCallNode::class);
	Assert::count(2, $calls);
	Assert::same(['1', '2', '3'], array_map(
		fn(PhpSyntax\Node $node) => $node->text,
		$file->find(PhpSyntax\Nodes\Scalar\IntegerNode::class),
	));

	// the predicate keeps the type of the class, so a slot of it is reached without instanceof
	Assert::same(['g'], array_map(
		fn(PhpSyntax\Nodes\Expression\FunctionCallNode $call) => $call->name->text,
		$file->find(
			PhpSyntax\Nodes\Expression\FunctionCallNode::class,
			fn(PhpSyntax\Nodes\Expression\FunctionCallNode $call) => count($call->arguments->items) === 1,
		),
	));

	Assert::same($calls[0], $file->findFirst(PhpSyntax\Nodes\Expression\FunctionCallNode::class));
	Assert::same('2', $file->findFirst(PhpSyntax\Nodes\Scalar\IntegerNode::class, fn($node) => $node->value === 2)?->text);
	Assert::null($file->findFirst(PhpSyntax\Nodes\Statement\ClassNode::class));
	Assert::null($file->findFirst(PhpSyntax\Node::class, fn() => false));

	// an interface every kind of declaration implements is a filter like any class
	$classes = (new Parser)->parse('<?php class A {} interface B {} $x = new class {};');
	Assert::count(3, $classes->find(PhpSyntax\Nodes\ClassLikeNode::class));
	Assert::type(
		PhpSyntax\Nodes\Statement\InterfaceNode::class,
		$classes->findFirst(PhpSyntax\Nodes\ClassLikeNode::class, fn($node) => !$node instanceof PhpSyntax\Nodes\Statement\ClassNode),
	);
});


test('getInnerComments() sees the comments hasInnerComment() counts', function () {
	$statement = parseStatement("/* a */ f(/* b */ 1); // c\n");
	Assert::same(['/* b */'], array_map(fn(PhpSyntax\Trivia $t) => $t->text, $statement->getInnerComments()));
	$first = $statement->getFirstToken();
	$last = $statement->getLastToken();
	Assert::same(['/* a */', '// c'], array_map(
		fn(PhpSyntax\Trivia $t) => $t->text,
		[...$first->getComments(), ...$last->getComments()],
	));
	Assert::same([], parseStatement("f(1);\n")->getInnerComments());
});


test('a comment is before a node, inside it or after it, and so of a token', function () {
	$texts = fn(array $comments) => array_map(fn(Trivia $trivia) => $trivia->text, $comments);
	$statement = parseStatement("/* a */ f(/* b */ 1); // c\n");
	Assert::true($statement->hasLeadingComment());
	Assert::true($statement->hasInnerComment());
	Assert::true($statement->hasTrailingComment());
	Assert::same(['/* a */'], $texts($statement->getLeadingComments()));
	Assert::same(['/* b */'], $texts($statement->getInnerComments()));
	Assert::same(['// c'], $texts($statement->getTrailingComments()));

	$call = $statement->expression;
	Assert::true($call->hasLeadingComment()); // the comment before the statement is the leading trivia of `f`, which the call starts with too
	Assert::same([], $call->getTrailingComments()); // the call ends at `)`, before the semicolon carrying it

	$plain = parseStatement("f(1);\n");
	Assert::false($plain->hasLeadingComment());
	Assert::false($plain->hasTrailingComment());
	Assert::same([], $plain->getLeadingComments());

	$tokens = $statement->getTokens();
	Assert::same([true, false, false, false, false], array_map(fn(PhpSyntax\Token $t) => $t->hasLeadingComment(), $tokens));
	Assert::same([false, true, false, false, true], array_map(fn(PhpSyntax\Token $t) => $t->hasTrailingComment(), $tokens));
	Assert::same(['/* a */'], $texts($tokens[0]->getLeadingComments()));
	Assert::same(['/* b */'], $texts($tokens[1]->getTrailingComments()));
	Assert::same(['// c'], $texts($tokens[4]->getTrailingComments()));
});


test('hasComment() sees a comment on either side of the token and nothing else', function () {
	$tokens = parseStatement("/* a */ f( 1 ); // c\n")->getTokens();
	Assert::same([true, false, false, false, true], array_map(fn(PhpSyntax\Token $t) => $t->hasComment(), $tokens));
	Assert::true(parseStatement("/** d */\nf();\n")->getFirstToken()->hasComment());
});


test('hasCommentUpTo() looks between two tokens of one file in their order and refuses any other interval', function () {
	$file = (new Parser)->parse('<?php a(); /* x */ b(); c();');
	[$a, , , , $b, , , , $c] = $file->getTokens();
	Assert::true($a->hasCommentUpTo($b));
	Assert::false($b->hasCommentUpTo($c));
	Assert::false($b->hasCommentUpTo($b));
	Assert::exception(fn() => $c->hasCommentUpTo($a), InvalidArgumentException::class, 'Token `a` stands before token `c`, which is where the interval starts.');
	Assert::exception(fn() => $a->hasCommentUpTo((new Parser)->parse('<?php d();')->getTokens()[0]), InvalidArgumentException::class, 'Token `d` stands in another file than token `a`.');
	Assert::exception(fn() => (clone $a)->hasCommentUpTo($b), LogicException::class, 'A token without a file has no order.');
});


test('what a trivia says about a comment', function () {
	$file = (new Parser)->parse("<?php\n// a\n# b\n/* c */\n/**\n * d\n * e\n */\nf();\n");
	$comments = [];
	foreach ($file->getIndex()->getTokens() as $token) {
		$comments = [...$comments, ...$token->getComments()];
	}

	[$line, $hash, $block, $doc] = $comments;
	Assert::true($line->isLineComment());
	Assert::true($hash->isLineComment());
	Assert::false($block->isLineComment());
	Assert::false($doc->isLineComment());
	Assert::false($line->isMultiLineComment());
	Assert::true($doc->isMultiLineComment());
	Assert::same(['a', 'b', 'c', "d\ne"], array_map(fn(PhpSyntax\Trivia $t) => $t->getCommentText(), $comments));

	// the tokenizer counts the spaces before the line break as part of a // comment; the text of it has none
	$spaced = (new Parser)->parse("<?php\nf(); // a  \n")->getIndex()->getTokens();
	Assert::same('a', $spaced[3]->getComments()[0]->getCommentText());
});


test('matches() compares token texts, not whitespace', function () {
	Assert::true(parseStatement("\$a[1] = 1;\n")->expression->matches(parseStatement("\$a [ 1 ]  =\n1;\n")->expression));
	Assert::false(parseStatement("\$a[1] = 1;\n")->expression->matches(parseStatement("\$a[2] = 1;\n")->expression));
	Assert::same(['$a', '[', '1', ']', '=', '1'], parseStatement("\$a [ 1 ]  = // c\n1;\n")->expression->getTokenTexts());
});


test('$plainName is the name without the dollar, and null where the name is an expression', function () {
	$variable = parseStatement("\$a;\n")->expression;
	assert($variable instanceof PhpSyntax\Nodes\Expression\VariableNode);
	Assert::same('a', $variable->plainName);

	// the name of a static property is written as a variable but is not one
	$fetch = parseStatement("A::\$b;\n")->expression;
	assert($fetch instanceof PhpSyntax\Nodes\Expression\StaticPropertyFetchNode);
	Assert::same('b', $fetch->plainName);
	Assert::same([], $fetch->find(PhpSyntax\Nodes\Expression\VariableNode::class));

	// A::$$b names the property the variable $b holds, so that one is a variable again
	$dynamic = parseStatement("A::\$\$b;\n")->expression;
	assert($dynamic instanceof PhpSyntax\Nodes\Expression\StaticPropertyFetchNode);
	Assert::null($dynamic->plainName);
	Assert::same('b', $dynamic->find(PhpSyntax\Nodes\Expression\VariableNode::class)[0]->plainName);

	$braced = parseStatement("A::\${'b'};\n")->expression;
	assert($braced instanceof PhpSyntax\Nodes\Expression\StaticPropertyFetchNode);
	Assert::null($braced->plainName);

	$property = parseStatement("\$a->b;\n")->expression;
	assert($property instanceof PhpSyntax\Nodes\Expression\PropertyFetchNode);
	Assert::same('b', $property->plainName);
	$property = parseStatement("\$a->\$b;\n")->expression;
	assert($property instanceof PhpSyntax\Nodes\Expression\PropertyFetchNode);
	Assert::null($property->plainName);
});


test('$plainName of a method call and a class constant fetch', function () {
	$name = function (string $code): ?string {
		$node = parseStatement("$code;\n")->expression;
		assert($node instanceof PhpSyntax\Nodes\Expression\MethodCallNode
			|| $node instanceof PhpSyntax\Nodes\Expression\StaticMethodCallNode
			|| $node instanceof PhpSyntax\Nodes\Expression\ClassConstantFetchNode);
		return $node->plainName;
	};
	Assert::same('b', $name('$a->b()'));
	Assert::same('b', $name('$a?->b()'));
	Assert::null($name('$a->$b()'));
	Assert::null($name('$a->{$b}()'));
	Assert::same('b', $name('A::b()'));
	Assert::null($name('A::{$b}()'));
	Assert::same('B', $name('A::B'));
	Assert::same('class', $name('A::class'));
	Assert::null($name('A::{$b}'));
});


test('isThis() and isOfThis() tell $this and its properties', function () {
	$isThis = function (string $code): bool {
		$variable = parseStatement("$code;\n")->expression;
		assert($variable instanceof PhpSyntax\Nodes\Expression\VariableNode);
		return $variable->isThis();
	};
	$isOfThis = function (string $code): bool {
		$fetch = parseStatement("$code;\n")->expression;
		assert($fetch instanceof PhpSyntax\Nodes\Expression\PropertyFetchNode);
		return $fetch->isOfThis();
	};
	Assert::true($isThis('$this'));
	Assert::false($isThis('$that'));
	Assert::false($isThis('$$this'));
	Assert::true($isOfThis('$this->a'));
	Assert::true($isOfThis('$this?->a'));
	Assert::true($isOfThis('$this->$a'));
	Assert::false($isOfThis('$that->a'));
	Assert::false($isOfThis('$this->a->b'));

	$call = parseStatement("\$this?->a();\n")->expression;
	assert($call instanceof PhpSyntax\Nodes\Expression\MethodCallNode);
	Assert::true($call->isOfThis());
	$call = parseStatement("\$that->a();\n")->expression;
	assert($call instanceof PhpSyntax\Nodes\Expression\MethodCallNode);
	Assert::false($call->isOfThis());
	$call = parseStatement("\$this->a()->b();\n")->expression;
	assert($call instanceof PhpSyntax\Nodes\Expression\MethodCallNode);
	Assert::false($call->isOfThis());
});


test('the plain and the combined assignment are told apart by the class', function () {
	$expression = fn(string $code) => parseStatement($code)->expression;

	Assert::type(PhpSyntax\Nodes\Expression\AssignmentNode::class, $expression("\$a = 1;\n"));
	Assert::type(PhpSyntax\Nodes\Expression\AssignmentByReferenceNode::class, $expression("\$a = &\$b;\n"));
	foreach (['+=', '-=', '*=', '/=', '.=', '%=', '&=', '|=', '^=', '<<=', '>>=', '**=', '??='] as $operator) {
		$combined = $expression("\$a $operator 1;\n");
		Assert::type(PhpSyntax\Nodes\Expression\CombinedAssignmentNode::class, $combined, $operator);
		Assert::same($operator, $combined->operator->text);
	}

	// the plain one takes a destructuring on the left, which is why its slot is wider; the grammar
	// takes a variable alone on the left of the combined one
	$destructuring = $expression("[\$a, \$b] = \$x;\n");
	assert($destructuring instanceof PhpSyntax\Nodes\Expression\AssignmentNode);
	Assert::type(PhpSyntax\Nodes\DestructuringNode::class, $destructuring->target);
	Assert::exception(
		fn() => (new Builder)->expression('[$a, $b] += 1'),
		PhpSyntax\ParseException::class,
	);
});


test('destructuring is a DestructuringNode however it is written, an array literal is not', function () {
	$target = function (string $code): PhpSyntax\Nodes\DestructuringNode {
		$assign = parseStatement($code)->expression;
		assert($assign instanceof PhpSyntax\Nodes\Expression\AssignmentNode);
		assert($assign->target instanceof PhpSyntax\Nodes\DestructuringNode);
		return $assign->target;
	};
	$second = function (PhpSyntax\Nodes\DestructuringNode $list): PhpSyntax\Nodes\ExpressionNode|PhpSyntax\Nodes\DestructuringNode {
		$item = $list->items[1];
		assert($item instanceof PhpSyntax\Nodes\ArrayItemNode);
		return $item->value;
	};

	// the short form has no keyword, and what nests in a target is a target too, either way round
	$short = $target("[\$a, [\$b]] = \$x;\n");
	Assert::null($short->listKeyword);
	Assert::same('[', $short->openDelimiter->text);
	Assert::type(PhpSyntax\Nodes\DestructuringNode::class, $second($short));

	$long = $target("list(\$a, [\$b]) = \$x;\n");
	Assert::same('list', $long->listKeyword?->text);
	Assert::type(PhpSyntax\Nodes\DestructuringNode::class, $second($long));

	// nothing of that happens where the array is a value
	$value = parseStatement("\$x = [\$a, [\$b]];\n")->expression;
	assert($value instanceof PhpSyntax\Nodes\Expression\AssignmentNode);
	Assert::type(PhpSyntax\Nodes\Expression\ArrayNode::class, $value->expression);
	Assert::type(PhpSyntax\Nodes\Expression\ArrayNode::class, parseStatement("[1, [2]];\n")->expression);
});


test('getDereferenceKind() tells which way the parent reaches in', function () {
	$inner = function (string $code): PhpSyntax\Nodes\ExpressionNode {
		$child = parseStatement($code)->expression->getChildren()[0];
		assert($child instanceof PhpSyntax\Nodes\ExpressionNode);
		return $child;
	};
	Assert::same(DereferenceKind::Fetch, $inner("(\$a)->b;\n")->getDereferenceKind());
	Assert::same(DereferenceKind::Fetch, $inner("(\$a)?->b;\n")->getDereferenceKind());
	Assert::same(DereferenceKind::Fetch, $inner("(\$a)->b();\n")->getDereferenceKind());
	Assert::same(DereferenceKind::Fetch, $inner("(\$a)[0];\n")->getDereferenceKind());
	Assert::same(DereferenceKind::Call, $inner("(\$a)();\n")->getDereferenceKind());
	Assert::same(DereferenceKind::StaticAccess, $inner("(\$a)::B;\n")->getDereferenceKind());
	Assert::same(DereferenceKind::StaticAccess, $inner("(\$a)::b();\n")->getDereferenceKind());
	Assert::same(DereferenceKind::StaticAccess, $inner("(\$a)::\$b;\n")->getDereferenceKind());

	// the other side of the same node is reached into by nothing
	$fetch = parseStatement("\$a[\$b];\n")->expression;
	assert($fetch instanceof PhpSyntax\Nodes\Expression\ArrayAccessNode);
	Assert::null($fetch->index?->getDereferenceKind());
	Assert::null($fetch->getDereferenceKind());
});


test('isDereferenced()', function () {
	$fetch = parseStatement("(new A)->b;\n")->expression;
	assert($fetch instanceof PhpSyntax\Nodes\Expression\PropertyFetchNode);
	Assert::true($fetch->object->isDereferenced());
	Assert::false($fetch->isDereferenced());
	$call = parseStatement("f(\$a)[0];\n")->expression;
	assert($call instanceof PhpSyntax\Nodes\Expression\ArrayAccessNode);
	Assert::true($call->expression->isDereferenced());
	Assert::false($call->index?->isDereferenced());
	$invoke = parseStatement("(new A)();\n")->expression;
	assert($invoke instanceof PhpSyntax\Nodes\Expression\FunctionCallNode);
	$callee = $invoke->name;
	assert($callee instanceof PhpSyntax\Nodes\ExpressionNode);
	Assert::true($callee->isDereferenced());
	Assert::false($invoke->isDereferenced());
});


test('the value an expression is written as', function () {
	$value = fn(string $code) => (new Builder)->expression($code)->toValue();
	Assert::same(1, $value('1'));
	Assert::same(1.5, $value('1.5'));
	Assert::same('a', $value("'a'"));
	Assert::same("b\n", $value('"b\n"'));
	Assert::same([true, false, null], [$value('true'), $value('FALSE'), $value('null')]);
	Assert::same(-1, $value('-1'));
	Assert::same(2.5, $value('+2.5'));
	Assert::same(1, $value('(1)'));
	Assert::same('ab', $value("<<<TXT\n\tab\n\tTXT"));
	Assert::same(['a' => 1, 'b' => [2, 3]], $value("['a' => 1, 'b' => [2, 3]]"));
	Assert::same([1, 2, 3], $value('[1, ...[2, 3]]'));
	// spreading gives its own numeric items new keys and keeps the string ones, the keys already there untouched
	Assert::same([5 => 'a', 6 => 'b'], $value('[5 => "a", ...["b"]]'));
	Assert::same(['k' => 2, 0 => 1], $value('["k" => 1, ...[1, "k" => 2]]'));

	// what a name stands for depends on what the code around it defines, so it is no value here
	foreach (['PHP_EOL', 'self::FOO', '$a', '1 + 2', '-PHP_INT_MAX', 'f()', '[&$a]', '"x{$a}"'] as $code) {
		Assert::false((new Builder)->expression($code)->hasValue(), $code);
		Assert::exception(
			fn() => $value($code),
			LogicException::class,
			"Expression `$code` has no value of its own.",
		);
	}

	// what is written inside says as much as what is written around it, and says it the same way
	Assert::false((new Builder)->expression('["x{$a}"]')->hasValue());
	Assert::exception(
		fn() => $value('["x{$a}"]'),
		LogicException::class,
		'Expression `["x{$a}"]` has no value of its own.',
	);

	Assert::true((new Builder)->expression('[1, null]')->hasValue());
	Assert::null($value('null')); // the value null is told apart from no value
});


test('whether parentheses may go', function () {
	$redundant = function (string $code): bool {
		$file = (new Parser)->parse("<?php $code");
		$node = $file->findFirst(PhpSyntax\Nodes\Expression\ParenthesizedNode::class);
		Assert::type(PhpSyntax\Nodes\Expression\ParenthesizedNode::class, $node);
		return $node->isRedundant();
	};

	// what binds tighter than the place it stands in needs no parentheses
	Assert::true($redundant('$x = ($a * $b) + 1;'));
	Assert::true($redundant('$x = 1 + ($a * $b);'));
	Assert::true($redundant('$x = ($a + $b) - 1;'));
	Assert::true($redundant('f(($a and $b));'));
	Assert::true($redundant('$x = ($a);'));
	Assert::true($redundant('return ($a = 1);'));
	Assert::true($redundant('$x = ($a)->b;'));

	// and what does not, keeps them
	Assert::false($redundant('$x = ($a + $b) * 2;'));
	Assert::false($redundant('$x = 1 - ($a - $b);')); // the right side of a left-leaning operator
	Assert::false($redundant('$x = ($a . "s") + 1;')); // PHP 8 binds the concatenation looser than the sum
	Assert::false($redundant('$x = ($a = 1) + 2;'));
	Assert::false($redundant('$x = (new A)->b;'));
	Assert::false($redundant('$x = ("str")();'));
	Assert::false($redundant('$x = (-$a) ** 2;'));
	Assert::false($redundant('$x = -(-$a);')); // it would read as a decrement
	Assert::false($redundant('$x = ($a ? 1 : 2) ? 3 : 4;'));
	Assert::false($redundant('$x = [(yield $a) => 1];'));
	Assert::false($redundant('$x = clone ($a ?: $b);'));
	// a call takes the name or the member before it for its own, which calls another thing entirely
	Assert::false($redundant('$x = ($a->b)();'));
	Assert::false($redundant('$x = (A::B)();'));
	Assert::true($redundant('$x = ($a->b)[0];'));

	// an operator with no left operand captures nothing before itself, so where nothing follows
	// the parentheses it stands bare however loosely it binds
	Assert::true($redundant('$x = (fn() => 1);'));
	Assert::true($redundant('$x = 1 + (fn() => 2);'));
	Assert::true($redundant('$x = $a ? 1 : (fn() => 2);'));
	Assert::true($redundant('$x = (print 1);'));
	Assert::true($redundant('$x = (throw new E);'));
	Assert::true($redundant('$x = (yield $a);'));
	Assert::true($redundant('$x = -(fn() => 1);'));
	// and everywhere else they are what bounds it
	Assert::false($redundant('$x = (fn() => 1) + 2;'));
	Assert::false($redundant('$x = (fn() => 1) ? 2 : 3;'));
	Assert::false($redundant('$x = $a ? (fn() => 1) : 2;'));
	Assert::false($redundant('$x = (print 1) . "s";'));
	// what follows may stand further up than beside them
	Assert::false($redundant('$x = $a + (yield $b) + 1;'));
	Assert::true($redundant('$x = [$a + (yield $b), 1];'));
	Assert::false($redundant('$x = [yield $a => (yield $b) => 1];')); // the => would become the inner yield's own
	Assert::true($redundant('$x = [yield $a => (yield $b => $c) => 1];'));
	Assert::false($redundant('$x = $a |> (fn($b) => $b);')); // a pipe takes an arrow function in parentheses only

	// a prefix operator is the same, save that it takes only what binds tighter than itself
	Assert::true($redundant('$x = (bool) (!$a);'));
	Assert::true($redundant('$x = (bool) (!$a) && $b;'));
	Assert::true($redundant('$x = (!$a) + 1;'));
	Assert::true($redundant('$x = $a ** (-$b);'));
	Assert::true($redundant('$x = (int) (-$a);'));
	Assert::false($redundant('$x = (bool) (!$a) instanceof B;'));
	Assert::false($redundant('$x = (!$a) instanceof B;'));
	Assert::false($redundant('$x = (int) (-$a) ** 2;'));
	// and so is whatever ends with such an operator, which takes what follows from inside
	Assert::false($redundant('$x = (@include $a) !== false;'));
	Assert::false($redundant('$x = ($a + yield $b) + 1;'));
	Assert::false($redundant('$x = ($a ** !$b) instanceof C;'));
	Assert::true($redundant('$x = ($a * !$b) * $c;')); // ! binds tighter than the product, so it takes nothing of it
	Assert::true($redundant('$x = [($a + yield $b), 1];'));

	// where a class is named, only a variable and what is read out of one stands there bare
	Assert::true($redundant('$x = new ($a)();'));
	Assert::true($redundant('$x = $a instanceof ($a->b);'));
	Assert::true($redundant('$x = new ($a->b[0]::$c)();'));
	Assert::true($redundant('$x = new (A::$b[0])();'));
	// all the way down: new f()->b would instantiate f, and new A::B[0] is no code
	Assert::false($redundant('$x = new (f()->b)();'));
	Assert::false($redundant('$x = new (A::B[0])();'));
	Assert::false($redundant('$x = new ("str")();'));
	Assert::false($redundant('$x = $a instanceof (A::B);'));
	// a pair of parentheses stands for whatever it holds, so a second pair around it adds nothing
	Assert::true($redundant('$x = new (($a + $b))();'));
	Assert::true($redundant('$x = (($a + $b))->c;'));

	// :: takes the name of a class on its left, so a name in the parentheses stays in them
	Assert::false($redundant('$x = (FOO)::class;')); // FOO::class would be the name FOO itself
	Assert::false($redundant('$x = (FOO)::BAR;'));
	Assert::false($redundant('$x = (FOO)::m();'));
	Assert::false($redundant('$x = (FOO)::$p;'));
	Assert::false($redundant('$x = (true)::class;'));
	// anything else on the left of :: is an expression either way, and -> and [] take one anyway
	Assert::true($redundant('$x = (A::B)::class;'));
	Assert::true($redundant('$x = (FOO)->x;'));
	Assert::true($redundant('$x = (FOO)[0];'));

	// a place bounded by a keyword, a bracket or a comma takes whatever is written in it
	Assert::true($redundant('f(($a and $b));'));
	Assert::true($redundant('foreach (($a ?: $b) as $v) {}'));
	Assert::true($redundant('class A { public $p = (1 + 2); }'));
	Assert::true($redundant('$x = match ($v) { ($a and $b) => 1 };'));
	Assert::false($redundant('$x = match ($v) { (yield $a) => 1 };')); // the arm would take the => for its own
});


test('isWritable() tells a place assigned to from a value read', function () {
	$target = function (string $code): PhpSyntax\Nodes\ExpressionNode {
		$assign = parseStatement("$code = 1;\n")->expression;
		assert($assign instanceof PhpSyntax\Nodes\Expression\AssignmentNode);
		assert($assign->target instanceof PhpSyntax\Nodes\ExpressionNode);
		return $assign->target;
	};
	Assert::true($target('$a')->isWritable());
	Assert::true($target('$a[0]')->isWritable());
	Assert::true($target('$a->b')->isWritable());
	Assert::true($target('A::$b')->isWritable());

	// a destructuring is no expression and the question never reaches it; see the DestructuringNode test above

	// ?-> reads and never writes, and the rest are values
	$expr = fn(string $code) => (new Builder)->expression($code);
	Assert::false($expr('$a?->b')->isWritable());
	// a ?-> anywhere in the chain the write reaches through counts, wherever it stands
	Assert::false($expr('$a?->b->c')->isWritable());
	Assert::false($expr('$a?->b[0][1]')->isWritable());
	Assert::false($expr('$a->b()?->c')->isWritable());
	Assert::false($expr('$a?->b::$c')->isWritable());
	Assert::false($expr('($a?->b)->c')->isWritable());
	// what stands beside that chain does not: an index and an argument are read, not written
	Assert::true($expr('$a[$b?->c]')->isWritable());
	Assert::true($expr('f($a?->b)[0]')->isWritable());
	Assert::true($expr('$a->b[$c?->d]')->isWritable());
	// a call is part of the chain: a ?-> before it counts, and so does the ?-> of the call itself
	Assert::false($expr('$o?->m()->p')->isWritable());
	Assert::false($expr('$o?->p->m()->q')->isWritable());
	Assert::false($expr('$o?->m()[0]')->isWritable());
	Assert::false($expr('($o?->m())->p')->isWritable());
	Assert::false($expr('$o?->m()::$p')->isWritable());
	Assert::true($expr('$o->m()->p')->isWritable());
	Assert::true($expr('$o->m($a?->b)->p')->isWritable());
	Assert::true($expr('($o?->p)()->q')->isWritable()); // the function call starts a chain of its own
	// a chain starting at a value of its own has no place to write to, one starting at a call or a named class has
	foreach ([
		'[1][0]',
		"'ab'[0]",
		'FOO[0]',
		'A::B[0]',
		'A::B->p',
		'(new A)->p',
		'(clone $x)->q',
		'(fn() => 1)->p',
		'($a ?: $b)->p',
		'($a = $b)->p',
	] as $code) {
		Assert::false($expr($code)->isWritable(), $code);
	}

	Assert::true($expr('f()[0]->p')->isWritable());
	Assert::true($expr('A::m()->p')->isWritable());
	Assert::true($expr('$o::$p->q')->isWritable());
	Assert::false($expr('f()')->isWritable());
	Assert::false($expr('A::B')->isWritable());
	Assert::false($expr('FOO')->isWritable());
	Assert::false($expr('($a)')->isWritable());
	Assert::false($expr('[1, 2]')->isWritable()); // a literal here, a destructuring only as a target
});


test('isWritten() tells what something writes, assigns, steps, unsets, binds or takes by reference', function () {
	$code = '<?php $w1 = 1; $w2 += 1; $w3++; [$w4, [$w5]] = f(); list($w6) = f(); unset($w7, $w8); global $w9;'
		. ' foreach ($r1 as $w10 => [$w11]) {} f(...$r2); echo $r3, $r4; $w12 = [$r5]; $w13[0] = 1; unset($w14[0]);'
		. ' $r6->a = 1; echo $r7[0]; static $w15; try {} catch (E $w16) {} function () use (&$w17, $r8) {};'
		. ' $w18 = &$w19; [$r9 => $w20, $r10 => [$r11 => $w21]] = f(); static $w22 = $r12; $w25 = [&$w23, $r14 => $r15];'
		. ' foreach ($r16 as [$r17 => $w24]) {}';
	foreach ((new Parser)->parse($code)->find(PhpSyntax\Nodes\Expression\VariableNode::class) as $variable) {
		Assert::same(str_starts_with($variable->text, '$w'), $variable->isWritten(), $variable->text);
	}

	// a property and an element are written as a variable is
	$fetches = (new Parser)->parse('<?php $o->p = 1; self::$s++; echo $o->q; $a[0] .= "x";')->find(
		PhpSyntax\Nodes\ExpressionNode::class,
		fn(PhpSyntax\Node $node) => $node instanceof PhpSyntax\Nodes\Expression\PropertyFetchNode
			|| $node instanceof PhpSyntax\Nodes\Expression\StaticPropertyFetchNode
			|| $node instanceof PhpSyntax\Nodes\Expression\ArrayAccessNode,
	);
	Assert::same([true, true, false, true], array_map(fn(PhpSyntax\Nodes\ExpressionNode $fetch) => $fetch->isWritten(), $fetches));
});


test('isRepeatableRead()', function () {
	Assert::true(parseStatement("\$a->b[C::D];\n")->expression->isRepeatableRead());
	Assert::false(parseStatement("\$a->b();\n")->expression->isRepeatableRead());
	Assert::false(parseStatement("\$a[f()];\n")->expression->isRepeatableRead());

	// every literal counts, whatever it is written with, and a string is worth what its pieces are
	Assert::true(parseStatement("__LINE__;\n")->expression->isRepeatableRead());
	Assert::true(parseStatement("\"a\$b\";\n")->expression->isRepeatableRead());
	Assert::true(parseStatement("<<<X\n\ta\n\tX;\n")->expression->isRepeatableRead());
	Assert::false(parseStatement("\"a{\$b->c()}\";\n")->expression->isRepeatableRead());

	// parentheses, a unary operator and an array run nothing of their own; unpacking and a reference do
	Assert::true(parseStatement("(\$a);\n")->expression->isRepeatableRead());
	Assert::true(parseStatement("-1;\n")->expression->isRepeatableRead());
	Assert::true(parseStatement("[1, 'k' => \$a[0]];\n")->expression->isRepeatableRead());
	Assert::false(parseStatement("[f()];\n")->expression->isRepeatableRead());
	Assert::false(parseStatement("[...\$a];\n")->expression->isRepeatableRead());
	Assert::false(parseStatement("[&\$a];\n")->expression->isRepeatableRead());
});


test('evaluatesToBoolean() tells an expression that yields a boolean whatever its operands', function () {
	foreach ([
		'$a === $b', '$a < 1', '$a && $b', '$a or $b', '!$a', '(bool) $a', '($a == 1)', '$a instanceof B', 'isset($a)',
		'empty($a)', 'true', 'FALSE',
	] as $code) {
		Assert::true((new Builder)->expression($code)->evaluatesToBoolean(), $code);
	}

	foreach (['$a', 'f()', '$a <=> $b', '$a ?? $b', '$a ? 1 : 2', '-$a', '(int) $a', 'null', '$a . $b', '$a = $b'] as $code) {
		Assert::false((new Builder)->expression($code)->evaluatesToBoolean(), $code);
	}
});


test('isLogical() tells the operators a condition is chained with', function () {
	foreach (['$a && $b', '$a || $b', '$a and $b', '$a OR $b', '$a xor $b'] as $code) {
		$node = (new Builder)->expression($code);
		Assert::true($node instanceof PhpSyntax\Nodes\Expression\BinaryOpNode && $node->isLogical(), $code);
	}

	foreach (['$a & $b', '$a === $b', '$a ?? $b', '$a . $b'] as $code) {
		$node = (new Builder)->expression($code);
		Assert::true($node instanceof PhpSyntax\Nodes\Expression\BinaryOpNode && !$node->isLogical(), $code);
	}
});


test('interruptsFlow() tells a statement after which the code does not go on, a block by its last statement', function () {
	$body = fn(string $code) => (new Parser)->parse("<?php if (\$a) $code")->find(PhpSyntax\Nodes\Statement\IfNode::class)[0]->body
		?? throw new LogicException('An if with a body is expected.');
	foreach (['return;', '{ f(); break; }', 'continue 2;', '{ goto end; }', 'throw new E;', '{ exit(1); }', 'die;', "{ return 1; ?>\n<?php }"] as $code) {
		Assert::true($body($code)->interruptsFlow(), $code);
	}

	foreach (['{}', 'f();', '{ return; f(); }', '{ if ($b) { return; } }', "{ return 1; ?>\nhtml<?php }"] as $code) {
		Assert::false($body($code)->interruptsFlow(), $code);
	}
});


test('isPreamble() is a BOM, a hashbang line, or both, and nothing more', function () {
	$first = function (string $code): InlineHtmlNode {
		$stmt = (new Parser)->parse($code)->statements[0];
		assert($stmt instanceof InlineHtmlNode);
		return $stmt;
	};
	Assert::true($first("\u{FEFF}<?php\n")->isPreamble());
	Assert::true($first("#!/usr/bin/env php\n<?php\n")->isPreamble());
	Assert::true($first("\u{FEFF}#!/usr/bin/env php\r\n<?php\n")->isPreamble());
	Assert::false($first("#!/usr/bin/env php\n\n<?php\n")->isPreamble()); // the blank line is output
	Assert::false($first("\n<?php\n")->isPreamble());
	Assert::false($first("<html>\n<?php\n")->isPreamble());
});


test('an operator tells how tightly it binds and which side it leans to', function () {
	$operator = function (string $code): PhpSyntax\Nodes\OperatorNode {
		$node = (new Builder)->expression($code);
		assert($node instanceof PhpSyntax\Nodes\OperatorNode);
		return $node;
	};
	Assert::same(Associativity::Left, $operator('$a - $b')->associativity);
	Assert::same(Associativity::Right, $operator('$a ?? $b')->associativity);
	Assert::same(Associativity::None, $operator('$a < $b')->associativity);
	Assert::same(Associativity::Right, $operator('$a = $b')->associativity);
	Assert::true($operator('$a * $b')->precedence > $operator('$a + $b')->precedence);
	Assert::true($operator('!$a')->precedence < $operator('-$a')->precedence);
	Assert::true($operator('$a and $b')->precedence < $operator('$a = $b')->precedence);
});
