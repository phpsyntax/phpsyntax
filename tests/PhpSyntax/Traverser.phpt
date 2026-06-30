<?php declare(strict_types=1);

use PhpSyntax\Node;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Expression\VariableNode;
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
