<?php declare(strict_types=1);

/**
 * Walking the whole tree with enter and leave callbacks, and steering the walk.
 *
 * Demonstrates: `Traverser::traverse()`, `TraverseAction::SkipChildren`, replacement during a walk
 * Usage:        php examples/codemod/traverser.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\{Builder, Node, Parser, Token, TraverseAction, Traverser};
use PhpSyntax\Nodes\Expression\ClosureNode;
use PhpSyntax\Nodes\Scalar\StringNode;

$code = sample(<<<'PHP'
	<?php
	function render(array $rows): string
	{
		$sep = ',';
		$format = function (array $row) {
			return implode(';', $row);
		};
		return implode($sep, array_map($format, $rows));
	}
	PHP);

$parser = new Parser;
$builder = new Builder;
$file = $parser->parse($code);

// enter and leave, over nodes and tokens alike
$depth = 0;
Traverser::traverse(
	$file,
	function (Node|Token $node) use (&$depth): void {
		if ($node instanceof Node) {
			echo str_repeat('  ', $depth++), shortClass($node), "\n";
		}
	},
	function (Node|Token $node) use (&$depth): void {
		$depth -= $node instanceof Node ? 1 : 0;
	},
);

// SkipChildren keeps the walk out of a subtree: here, out of nested closures
echo "\n";
Traverser::traverse($file, function (Node|Token $node): ?TraverseAction {
	if ($node instanceof ClosureNode) {
		echo "not descending into the closure\n";
		return TraverseAction::SkipChildren;
	} elseif ($node instanceof StringNode) {
		echo 'string outside the closure: ', $node->toValue(), "\n";
	}

	return null;
});

// a callback may rewrite the node it was given, and the replacement here contains a node the
// callback matches as well. The walk does not descend into a replacement, so this terminates
// instead of rewriting its own output forever.
echo "\n";
$rewrites = 0;
Traverser::traverse($file, function (Node|Token $node) use ($builder, &$rewrites): void {
	if ($node instanceof StringNode && $node->toValue() === ',') {
		$node->replaceWith($builder->expression("self::SEPARATOR . ','"));
		$rewrites++;
	}
});

echo "rewrites: $rewrites\n";

printDiff($code, (string) $file);
