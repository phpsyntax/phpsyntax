<?php declare(strict_types=1);

use PhpSyntax\Nodes\Expression\{ArrayNode, FunctionCallNode};
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use PhpSyntax\Parser;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


/** @return SeparatedNodeList<PhpSyntax\Nodes\ArrayItemNode|PhpSyntax\Nodes\EmptyArrayItemNode> */
function items(string $code): SeparatedNodeList
{
	$stmt = (new Parser)->parse($code)->statements->getItems()[0];
	Assert::type(ExpressionStatementNode::class, $stmt);
	Assert::type(ArrayNode::class, $stmt->expression);
	return $stmt->expression->items;
}


function item(string $code): PhpSyntax\Nodes\ArrayItemNode
{
	$item = items("<?php [$code];")->getItems()[0];
	Assert::type(PhpSyntax\Nodes\ArrayItemNode::class, $item);
	return clone $item;
}


test('one-line list: the separator is modeled on the existing ones', function () {
	$list = items('<?php [1, 2];');
	$list->append(item('3'));
	Assert::same('<?php [1, 2, 3]', (string) $list->parent);
	$list->insert(0, item('0'));
	Assert::same('<?php [0, 1, 2, 3]', (string) $list->parent);
	$list->insert(2, item('x'));
	Assert::same('<?php [0, 1, x, 2, 3]', (string) $list->parent);
	Assert::same(5, count($list));
	Assert::exception(fn() => $list->insert(9, item('9')), OutOfRangeException::class, 'Index 9 is out of range, the list has %d% items.');
});


test('empty and single-item lists', function () {
	$list = items('<?php [];');
	$list->append(item('1'));
	Assert::same('<?php [1]', (string) $list->parent);
	$list->append(item('2'));
	Assert::same('<?php [1, 2]', (string) $list->parent);
	Assert::exception(fn() => items('<?php [];')->append(item('1'), new PhpSyntax\Token(ord(','), ',')), LogicException::class, 'The first item has no separator before it.');
});


test('multi-line list: the item inherits the indentation and the separator its line ending', function () {
	$list = items("<?php [\n\t1,\n\t2,\n];");
	$list->append(item('3'));
	Assert::same("<?php [\n\t1,\n\t2,\n\t3,\n]", (string) $list->parent);
	Assert::true($list->hasTrailingSeparator());
	$list->insert(0, item('0'));
	Assert::same("<?php [\n\t0,\n\t1,\n\t2,\n\t3,\n]", (string) $list->parent);

	$list = items("<?php [\n\t1\n];");
	$list->append(item('2'));
	Assert::same("<?php [\n\t1,\n\t2\n]", (string) $list->parent);
});


test('a separator carrying a comment models nothing: the comment would be copied', function () {
	$list = items('<?php [1, /* unique */ 2];');
	$list->append(item('3'));
	Assert::same('<?php [1, /* unique */ 2, 3]', (string) $list->parent);

	// another separator stands in for it, with the formatting it has
	$list = items("<?php [\n\t1, // why\n\t2,\n];");
	$list->append(item('3'));
	Assert::same("<?php [\n\t1, // why\n\t2,\n\t3,\n]", (string) $list->parent);
});


test('an item inserted between two keeps the comment after the one before it in place', function () {
	$list = items("<?php [\n\t1, // why\n\t2,\n];");
	$list->insert(1, item('x'));
	Assert::same("<?php [\n\t1, // why\n\tx,\n\t2,\n]", (string) $list->parent);

	$list = items('<?php [1, /* one */ 2];');
	$list->insert(1, item('x'));
	Assert::same('<?php [1, /* one */ x, 2]', (string) $list->parent);
});


test('a trailing separator in a one-line list models no separator between items', function () {
	$list = items('<?php [1, 2,];');
	$list->append(item('3'));
	Assert::same('<?php [1, 2, 3,]', (string) $list->parent);

	$list = items('<?php [1,];');
	$list->append(item('2'));
	Assert::same('<?php [1, 2,]', (string) $list->parent);
});


test('an empty list takes no trailing separator', function () {
	Assert::exception(
		fn() => items('<?php [];')->setTrailingSeparator(new PhpSyntax\Token(ord(','), ',')),
		LogicException::class,
		'An empty list has no item a trailing separator could follow.',
	);
});


test('an explicit separator is used as given', function () {
	$list = items('<?php [1, 2];');
	$list->append(item('3'), new PhpSyntax\Token(ord(','), ','));
	Assert::same('<?php [1, 2,3]', (string) $list->parent);
});


test('removeItem takes the separator after the item, or before the last one', function () {
	$stmt = (new Parser)->parse('<?php f($a, $b, $c,);')->statements->getItems()[0];
	Assert::type(ExpressionStatementNode::class, $stmt);
	Assert::type(FunctionCallNode::class, $stmt->expression);
	$args = $stmt->expression->arguments->items;
	[$a, $b, $c] = $args->getItems();
	$args->removeItem($b);
	Assert::same('<?php f($a, $c,)', (string) $stmt->expression);
	$args->removeItem($c);
	Assert::same('<?php f($a,)', (string) $stmt->expression);
	$args->removeItem($a);
	Assert::same('<?php f()', (string) $stmt->expression);
	Assert::false($args->hasTrailingSeparator());
});
