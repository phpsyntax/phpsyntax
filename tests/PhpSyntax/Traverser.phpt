<?php declare(strict_types=1);

use PhpSyntax\{Node, Parser, Token, TraverseAction, Traverser};
use PhpSyntax\Nodes\Expression\{FunctionCallNode, VariableNode};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function label(Node|Token $node): string
{
	return $node instanceof Token ? "'$node->text'" : substr($node::class, strrpos($node::class, '\\') + 1);
}


test('pre-order with enter and leave', function () {
	$file = (new Parser)->parse('<?php $a;');
	$log = [];
	Traverser::traverse(
		$file,
		function (Node|Token $node) use (&$log) { $log[] = '>' . label($node); },
		function (Node|Token $node) use (&$log) { $log[] = '<' . label($node); },
	);
	Assert::same([
		'>FileNode', '>PlainNodeList', '>ExpressionStatementNode', '>VariableNode', ">'\$a'", "<'\$a'", '<VariableNode',
		">';'", "<';'", '<ExpressionStatementNode', '<PlainNodeList', ">''", "<''", '<FileNode',
	], $log);
});


test('a callback keeps the walk out of a subtree or ends it', function () {
	$file = (new Parser)->parse('<?php f($a); $b;');
	$visited = [];
	Traverser::traverse($file, function (Node|Token $node) use (&$visited) {
		$visited[] = label($node);
		return $node instanceof FunctionCallNode ? TraverseAction::SkipChildren : null;
	});
	Assert::contains('FunctionCallNode', $visited);
	Assert::notContains("'\$a'", $visited);

	$visited = [];
	Traverser::traverse($file, function (Node|Token $node) use (&$visited) {
		$visited[] = label($node);
		return $node instanceof FunctionCallNode ? TraverseAction::Stop : null;
	});
	Assert::same('FunctionCallNode', end($visited));
});


test('a stop enters nothing more and leaves what it has entered', function () {
	$file = (new Parser)->parse('<?php $a;');
	$log = [];
	Traverser::traverse(
		$file,
		function (Node|Token $node) use (&$log) {
			$log[] = '>' . label($node);
			return $node instanceof Token ? TraverseAction::Stop : null;
		},
		function (Node|Token $node) use (&$log) { $log[] = '<' . label($node); },
	);
	Assert::same([
		'>FileNode', '>PlainNodeList', '>ExpressionStatementNode', '>VariableNode', ">'\$a'",
		"<'\$a'", '<VariableNode', '<ExpressionStatementNode', '<PlainNodeList', '<FileNode',
	], $log);

	// a stop from leave ends the walk the same way, the nodes below it already left
	$log = [];
	Traverser::traverse(
		$file,
		function (Node|Token $node) use (&$log) { $log[] = '>' . label($node); },
		function (Node|Token $node) use (&$log) {
			$log[] = '<' . label($node);
			return $node instanceof VariableNode ? TraverseAction::Stop : null;
		},
	);
	Assert::same([
		'>FileNode', '>PlainNodeList', '>ExpressionStatementNode', '>VariableNode', ">'\$a'", "<'\$a'",
		'<VariableNode', '<ExpressionStatementNode', '<PlainNodeList', '<FileNode',
	], $log);
});


test('a callback may start a walk of its own, and a value it returns of its own steers nothing', function () {
	$file = (new Parser)->parse('<?php f($a); g($b);');
	$outer = $inner = 0;
	Traverser::traverse($file, function (Node|Token $node) use (&$outer, &$inner) {
		$outer++;
		if ($node instanceof FunctionCallNode) {
			Traverser::traverse($node, function () use (&$inner) {
				$inner++;
				return TraverseAction::Stop;
			});
		}

		return 1; // what a counter returns, no action
	});
	Assert::same(2, $inner);
	Assert::same(count($file->getTokens()) + count($file->find(Node::class)) + 1, $outer);
});
