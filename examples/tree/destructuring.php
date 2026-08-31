<?php declare(strict_types=1);

/**
 * The class of a node follows what the code means, not which of its spellings was used, and the
 * spelling is still there to print back.
 *
 * Demonstrates: DestructuringNode over both spellings of destructuring, ArrayNode as a literal only
 * Usage:        php examples/tree/destructuring.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Builder;
use PhpSyntax\Nodes\ArrayItemNode;

$builder = new Builder;

printf("%-26s %-18s %-10s %s\n", 'written', 'node', 'keyword', 'nested item');

foreach ([
	'[$a, $b] = $row',
	'list($a, $b) = $row',
	'[$a, [$b, $c]] = $row',
	'list($a, [$b, $c]) = $row',
] as $source) {
	$assign = $builder->expression($source);
	$target = $assign->target;
	$nested = $target->items[1];
	printf(
		"%-26s %-18s %-10s %s\n",
		$source,
		shortClass($target),
		json_encode($target->listKeyword?->text),
		$nested instanceof ArrayItemNode ? shortClass($nested->value) : '',
	);
}

// the very same brackets on the other side of the assignment are a literal and stay an ArrayNode
$literal = $builder->expression('$row = [$a, $b]');
printf("%-26s %-12s\n", '$row = [$a, $b]', shortClass($literal->expression));

// the spelling is in the slots, so the text comes back exactly as it was written
echo "\n";
foreach (['list($a, [$b]) = $row', '[$a, list($b)] = $row'] as $source) {
	printf("%-26s prints back as %s\n", $source, (string) $builder->expression($source));
}
