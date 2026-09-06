<?php declare(strict_types=1);

/**
 * Replacing and removing nodes: the surrounding whitespace and comments stay where they belong.
 *
 * Demonstrates: slot assignment, Node::replaceWith(), Node::remove(), CommentPolicy
 * Usage:        php examples/mutation/replace.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\CommentPolicy;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use PhpSyntax\Nodes\Statement\IfNode;
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	function ship(array $order): string
	{
		// TODO: drop this once the old client is gone
		$legacy = compat_normalize($order);

		if (count($order['items']) === 0) {   // nothing to ship
			return 'empty';
		}

		return implode(',', array_map('strval', $order['items']));
	}
	PHP);

$parser = new Parser;
$file = $parser->parse($code);

// 1. a slot is written by assignment: the old node leaves the tree, the new one joins it,
//    and the whitespace around the slot is untouched
$if = $file->find(IfNode::class)[0];
$if->condition = $parser->parseExpression('$order[\'items\'] === []');

// 2. replaceWith() does the same from the child's side, keeping the trivia of the old node
foreach ($file->find(FunctionCallNode::class) as $call) {
	if ($call->name instanceof NameNode && $call->name->equals('implode')) {
		$call->replaceWith($parser->parseExpression("implode(',', \$order['items'])"));
	}
}

// 3. remove a statement: alone on its lines it takes the lines with it, and the comment above it
//    goes where the policy says instead of being destroyed
$file->find(ExpressionStatementNode::class)[0]->remove(CommentPolicy::MoveToNextToken);

printDiff($code, (string) $file);

echo "\n--- the result ---\n", (string) $file, "\n";
