<?php declare(strict_types=1);

/**
 * Moving statements around: a feature flag that is always on dropped with its braces, a debugging
 * line removed with its blank line, and a statement inserted after its neighbor.
 *
 * Demonstrates: `BlockNode::unwrap()`, `Indentation::shift()`, `Node::remove(mergeBlankLines: true)`,
 *               `getNextSibling()`, `getPreviousSibling()`, `PlainNodeList::insertAfter()`
 * Usage:        php examples/mutation/statements.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Builder;
use PhpSyntax\Indentation;
use PhpSyntax\Nodes\Expression\{ConstantFetchNode, FunctionCallNode, MethodCallNode};
use PhpSyntax\Nodes\Statement\{FunctionNode, IfNode};
use PhpSyntax\{Parser, Style};

$code = sample(<<<'PHP'
	<?php
	function checkout(Cart $cart): Order
	{
		$order = new Order($cart);

		if (NEW_TAX_RULES) { // on everywhere since March
			$order->applyTax(new TaxRate('EU'));
			$order->round(2);
		}

		dump($order);

		$order->save();
		return $order;
	}
	PHP);

$file = new Parser()->parse($code);
$builder = new Builder;

// 1. the flag is always on, so the `if` goes and its block takes its place among the statements;
//    unwrap() drops the braces and keeps the comment, shift() moves the lines a level up
$if = $file->findFirst(IfNode::class, fn(IfNode $if) => $if->condition instanceof ConstantFetchNode);
$block = $if->body;
$if->replaceWith($block);
foreach ($block->statements as $statement) {
	Indentation::shift($statement, -1, new Style);
}
$block->unwrap();

echo "a feature flag that is always on:\n";
printDiff($code, $step = (string) $file);

// 2. a debugging line between two blank lines; without mergeBlankLines both would stay
$dump = $file->findFirst(FunctionCallNode::class, fn(FunctionCallNode $call) => $call->name->text === 'dump');
$dump->parent->remove(mergeBlankLines: true);

echo "\na statement removed with its blank line:\n";
printDiff($step, $step = (string) $file);

// 3. the neighbors of a statement are the items of its list: the event goes right after the save
$save = $file->findFirst(MethodCallNode::class, fn(MethodCallNode $call) => $call->plainName === 'save')->parent;
$return = $save->getNextSibling();
$file->findFirst(FunctionNode::class)->body->statements->insertAfter($save, $builder->statement('event(new OrderPlaced($order));'));

echo "\na statement inserted after its neighbor:\n";
printDiff($step, (string) $file);
echo 'now before the return: ', $return->getPreviousSibling()->text, "\n";
