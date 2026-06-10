<?php declare(strict_types=1);

use PhpSyntax\Nodes\Statement\{ExpressionStatementNode, InlineHtmlNode};
use PhpSyntax\Parser;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


function parseStatement(string $code): ExpressionStatementNode
{
	$stmt = (new Parser)->parse("<?php\n$code")->statements->getItems()[0];
	assert($stmt instanceof ExpressionStatementNode);
	return $stmt;
}


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
});


test('destructuring is a ListNode however it is written, an array literal is not', function () {
	$target = function (string $code): PhpSyntax\Nodes\Expression\ListNode {
		$assign = parseStatement($code)->expression;
		assert($assign instanceof PhpSyntax\Nodes\Expression\AssignmentNode);
		assert($assign->target instanceof PhpSyntax\Nodes\Expression\ListNode);
		return $assign->target;
	};
	$second = function (PhpSyntax\Nodes\Expression\ListNode $list): PhpSyntax\Nodes\ExpressionNode|PhpSyntax\Nodes\Expression\ListNode {
		$item = $list->items->getItems()[1];
		assert($item instanceof PhpSyntax\Nodes\ArrayItemNode);
		return $item->value;
	};

	// the short form has no keyword, and what nests in a target is a target too, either way round
	$short = $target("[\$a, [\$b]] = \$x;\n");
	Assert::null($short->listKeyword);
	Assert::same('[', $short->openDelimiter->text);
	Assert::type(PhpSyntax\Nodes\Expression\ListNode::class, $second($short));

	$long = $target("list(\$a, [\$b]) = \$x;\n");
	Assert::same('list', $long->listKeyword?->text);
	Assert::type(PhpSyntax\Nodes\Expression\ListNode::class, $second($long));

	// nothing of that happens where the array is a value
	$value = parseStatement("\$x = [\$a, [\$b]];\n")->expression;
	assert($value instanceof PhpSyntax\Nodes\Expression\AssignmentNode);
	Assert::type(PhpSyntax\Nodes\Expression\ArrayNode::class, $value->expression);
	Assert::type(PhpSyntax\Nodes\Expression\ArrayNode::class, parseStatement("[1, [2]];\n")->expression);
});


test('isPreamble() is a BOM, a hashbang line, or both, and nothing more', function () {
	$first = function (string $code): InlineHtmlNode {
		$stmt = (new Parser)->parse($code)->statements->getItems()[0];
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
