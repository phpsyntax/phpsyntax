<?php declare(strict_types=1);

/**
 * Building new code out of nodes you already hold: a call around an expression, a method call on
 * a new object, and the rewrite of `$a = $a + $b` into `$a += $b`.
 *
 * Demonstrates: `FunctionCallNode::of()`, `MethodCallNode::of()`, `ArgumentListNode::of()`, `CombinedAssignmentNode::of()`, `Node::withoutEdgeTrivia()`
 * Usage:        php examples/mutation/factories.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\ArgumentListNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\Expression\AssignmentNode;
use PhpSyntax\Nodes\Expression\BinaryOpNode;
use PhpSyntax\Nodes\Expression\CombinedAssignmentNode;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Expression\MethodCallNode;
use PhpSyntax\Nodes\Expression\NewNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	function invoice(array $cart, float $vat): string
	{
		$total = $cart['net'];
		$total = $total + $vat;   // VAT is always added last

		$label = trim($cart['label']);

		return new Invoice($label)->format($total);
	}
	PHP);

$parser = new Parser;
$file = $parser->parse($code);

// 1. $a = $a + $b becomes $a += $b; the operands go in as copies, because a factory takes no node
//    that still stands in the file, and replaceWith() keeps the comment after the statement
foreach ($file->find(AssignmentNode::class) as $assign) {
	[$target, $sum] = [$assign->target, $assign->expression];
	if ($target instanceof ExpressionNode && $sum instanceof BinaryOpNode && $sum->operator->is('+') && $target->matches($sum->left)) {
		$assign->replaceWith(CombinedAssignmentNode::of(
			$target->withoutEdgeTrivia(),
			'+=',
			$sum->right->withoutEdgeTrivia(),
		));
	}
}

// 2. an expression wrapped in a call: the old call becomes the argument of the new one
$trim = must($file->findFirst(FunctionCallNode::class));
$trim->replaceWith(FunctionCallNode::of(
	NameNode::fromText('htmlspecialchars'),
	ArgumentListNode::of($trim->withoutEdgeTrivia()),
));

// 3. a method called on a new object: the factory writes `new` in parentheses, as it does with
//    anything a call could not be sure to reach into bare
$format = must($file->findFirst(MethodCallNode::class));
$format->replaceWith(MethodCallNode::of(
	NewNode::of(NameNode::fromText('Receipt'), ArgumentListNode::of($parser->parseExpression('$label'))),
	'render',
	ArgumentListNode::of($parser->parseExpression('$total')),
));

printDiff($code, (string) $file);

// what a factory refuses: a node that still stands in the file, before it has changed anything
$live = must($file->findFirst(FunctionCallNode::class));
try {
	FunctionCallNode::of(NameNode::fromText('strval'), ArgumentListNode::of($live));
} catch (LogicException $e) {
	echo "\nLogicException: ", $e->getMessage(), "\n";
}
