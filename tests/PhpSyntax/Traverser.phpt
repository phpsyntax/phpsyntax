<?php declare(strict_types=1);

use PhpSyntax\{Builder, Node, Parser, Token, TraverseAction, Traverser};
use PhpSyntax\Nodes\Expression\{FunctionCallNode, VariableNode};
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
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


test('a replaced node is not descended into, but is left like any other', function () {
	$file = (new Parser)->parse('<?php $a; $b;');
	$log = [];
	Traverser::traverse(
		$file,
		function (Node|Token $node) use (&$log) {
			$log[] = '>' . label($node);
			if ($node instanceof VariableNode && $node->name instanceof Token && $node->name->text === '$a') {
				$node->replaceWith((new Builder)->expression('$x'));
			}
		},
		function (Node|Token $node) use (&$log) { $log[] = '<' . label($node); },
	);
	Assert::same('<?php $x; $b;', (string) $file);
	Assert::same([
		'>FileNode', '>PlainNodeList', '>ExpressionStatementNode', '>VariableNode', '<VariableNode',
		">';'", "<';'", '<ExpressionStatementNode',
		'>ExpressionStatementNode', '>VariableNode', ">'\$b'", "<'\$b'", '<VariableNode', ">';'", "<';'", '<ExpressionStatementNode',
		'<PlainNodeList', ">''", "<''", '<FileNode',
	], $log);
});


test('a callback keeping a state of its own sees a leave for every enter', function () {
	$file = (new Parser)->parse('<?php $a; $b;');
	$depth = 0;
	$deepest = 0;
	Traverser::traverse(
		$file,
		function (Node|Token $node) use (&$depth, &$deepest) {
			$deepest = max($deepest, ++$depth);
			if ($node instanceof VariableNode) {
				$node->replaceWith((new Builder)->expression('f()'));
			}
		},
		function () use (&$depth) { $depth--; },
	);
	Assert::same('<?php f(); f();', (string) $file);
	Assert::same(0, $depth); // every enter was paid back
	Assert::same(4, $deepest);
});


test('siblings removed meanwhile are skipped, inserted ones wait for the next walk', function () {
	$file = (new Parser)->parse('<?php $a; $b; $c;');
	$visited = [];
	Traverser::traverse($file, function (Node|Token $node) use (&$visited, $file) {
		if ($node instanceof ExpressionStatementNode) {
			$visited[] = $name = $node->expression->getFirstToken()->text;
			if ($name === '$a') {
				$file->statements[1]->remove();
				$file->statements->append((new Builder)->statement('$d;'));
			}
		}
	});
	Assert::same(['$a', '$c'], $visited);
	Assert::same('<?php $a;  $c;$d;', (string) $file);
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
