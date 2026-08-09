<?php declare(strict_types=1);

use PhpSyntax\Node;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use PhpSyntax\Parser;
use PhpSyntax\Token;
use PhpSyntax\Traverser;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function label(Node|Token $node): string
{
	return $node instanceof Token ? "'$node->text'" : substr($node::class, strrpos($node::class, '\\') + 1);
}


test('pre-order with enter and leave', function () {
	$file = (new Parser)->parse('<?php $a;');
	$log = [];
	new Traverser()->traverse(
		$file,
		function (Node|Token $node) use (&$log) { $log[] = '>' . label($node); },
		function (Node|Token $node) use (&$log) { $log[] = '<' . label($node); },
	);
	Assert::same([
		'>FileNode', '>NodeList', '>ExpressionStatementNode', '>VariableNode', ">'\$a'", "<'\$a'", '<VariableNode',
		">';'", "<';'", '<ExpressionStatementNode', '<NodeList', ">''", "<''", '<FileNode',
	], $log);
});


test('a replaced node is not descended into, but is left like any other', function () {
	$file = (new Parser)->parse('<?php $a; $b;');
	$log = [];
	new Traverser()->traverse(
		$file,
		function (Node|Token $node) use (&$log) {
			$log[] = '>' . label($node);
			if ($node instanceof VariableNode && $node->name instanceof Token && $node->name->text === '$a') {
				$node->replaceWith((new Parser)->parseExpression('$x'));
			}
		},
		function (Node|Token $node) use (&$log) { $log[] = '<' . label($node); },
	);
	Assert::same('<?php $x; $b;', (string) $file);
	Assert::same([
		'>FileNode', '>NodeList', '>ExpressionStatementNode', '>VariableNode', '<VariableNode',
		">';'", "<';'", '<ExpressionStatementNode',
		'>ExpressionStatementNode', '>VariableNode', ">'\$b'", "<'\$b'", '<VariableNode', ">';'", "<';'", '<ExpressionStatementNode',
		'<NodeList', ">''", "<''", '<FileNode',
	], $log);
});


test('a callback keeping a state of its own sees a leave for every enter', function () {
	$file = (new Parser)->parse('<?php $a; $b;');
	$depth = 0;
	$deepest = 0;
	new Traverser()->traverse(
		$file,
		function (Node|Token $node) use (&$depth, &$deepest) {
			$deepest = max($deepest, ++$depth);
			if ($node instanceof VariableNode) {
				$node->replaceWith((new Parser)->parseExpression('f()'));
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
	new Traverser()->traverse($file, function (Node|Token $node) use (&$visited, $file) {
		if ($node instanceof ExpressionStatementNode) {
			$visited[] = $name = $node->expression->getFirstToken()?->text;
			if ($name === '$a') {
				$file->statements->getItems()[1]->remove();
				$file->statements->append((new Parser)->parseStatement('$d;'));
			}
		}
	});
	Assert::same(['$a', '$c'], $visited);
	Assert::same('<?php $a;  $c;$d;', (string) $file);
});


test('a callback keeps the walk out of a subtree or ends it', function () {
	$file = (new Parser)->parse('<?php f($a); $b;');
	$visited = [];
	new Traverser()->traverse($file, function (Node|Token $node) use (&$visited) {
		$visited[] = label($node);
		return $node instanceof FunctionCallNode ? Traverser::DontTraverseChildren : null;
	});
	Assert::contains('FunctionCallNode', $visited);
	Assert::notContains("'\$a'", $visited);

	$visited = [];
	new Traverser()->traverse($file, function (Node|Token $node) use (&$visited) {
		$visited[] = label($node);
		return $node instanceof FunctionCallNode ? Traverser::StopTraversal : null;
	});
	Assert::same('FunctionCallNode', end($visited));
});


test('a stop enters nothing more and leaves what it has entered', function () {
	$file = (new Parser)->parse('<?php $a;');
	$log = [];
	new Traverser()->traverse(
		$file,
		function (Node|Token $node) use (&$log) {
			$log[] = '>' . label($node);
			return $node instanceof Token ? Traverser::StopTraversal : null;
		},
		function (Node|Token $node) use (&$log) { $log[] = '<' . label($node); },
	);
	Assert::same([
		'>FileNode', '>NodeList', '>ExpressionStatementNode', '>VariableNode', ">'\$a'",
		"<'\$a'", '<VariableNode', '<ExpressionStatementNode', '<NodeList', '<FileNode',
	], $log);

	// a stop from leave ends the walk the same way, the nodes below it already left
	$log = [];
	new Traverser()->traverse(
		$file,
		function (Node|Token $node) use (&$log) { $log[] = '>' . label($node); },
		function (Node|Token $node) use (&$log) {
			$log[] = '<' . label($node);
			return $node instanceof VariableNode ? Traverser::StopTraversal : null;
		},
	);
	Assert::same([
		'>FileNode', '>NodeList', '>ExpressionStatementNode', '>VariableNode', ">'\$a'", "<'\$a'",
		'<VariableNode', '<ExpressionStatementNode', '<NodeList', '<FileNode',
	], $log);
});
