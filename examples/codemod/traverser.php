<?php declare(strict_types=1);

/**
 * Walking the whole tree with enter and leave callbacks, and steering the walk.
 *
 * Demonstrates: Traverser::traverse(), DontTraverseChildren, StopTraversal, replacement during a walk
 * Usage:        php examples/codemod/traverser.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Node;
use PhpSyntax\Nodes\Expression\ClosureNode;
use PhpSyntax\Nodes\Scalar\StringNode;
use PhpSyntax\Parser;
use PhpSyntax\Token;
use PhpSyntax\Traverser;

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
$file = $parser->parse($code);

// enter and leave, over nodes and tokens alike
$depth = 0;
new Traverser()->traverse(
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

// DontTraverseChildren keeps the walk out of a subtree: here, out of nested closures
echo "\n";
new Traverser()->traverse($file, function (Node|Token $node): ?int {
	if ($node instanceof ClosureNode) {
		echo "not descending into the closure\n";
		return Traverser::DontTraverseChildren;
	} elseif ($node instanceof StringNode) {
		echo 'string outside the closure: ', $node->value, "\n";
	}

	return null;
});

// a callback may rewrite the node it was given, and the replacement here contains a node the
// callback matches as well. The walk does not descend into a replacement, so this terminates
// instead of rewriting its own output forever.
echo "\n";
$rewrites = 0;
new Traverser()->traverse($file, function (Node|Token $node) use ($parser, &$rewrites): void {
	if ($node instanceof StringNode && $node->value === ',') {
		$node->replaceWith($parser->parseExpression("self::SEPARATOR . ','"));
		$rewrites++;
	}
});

echo "rewrites: $rewrites\n";

printDiff($code, (string) $file);
