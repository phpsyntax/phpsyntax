<?php declare(strict_types=1);

use PhpSyntax\{Builder, Parser};
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
	Assert::same('2', $file->findFirst(PhpSyntax\Nodes\Scalar\IntegerNode::class, fn($node) => $node->toValue() === 2)?->text);
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
