<?php declare(strict_types=1);

/**
 * The class of a node follows what the code means, not which of its spellings was used, and the
 * spelling is still there to print back.
 *
 * Demonstrates: ListNode over both spellings of destructuring, ArrayNode as a literal only
 * Usage:        php examples/tree/destructuring.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\ArrayItemNode;
use PhpSyntax\Nodes\Expression\AssignmentNode;
use PhpSyntax\Nodes\Expression\ListNode;
use PhpSyntax\Parser;

$parser = new Parser;

printf("%-26s %-12s %-10s %s\n", 'written', 'node', 'keyword', 'nested item');

foreach ([
	'[$a, $b] = $row',
	'list($a, $b) = $row',
	'[$a, [$b, $c]] = $row',
	'list($a, [$b, $c]) = $row',
] as $source) {
	$assign = $parser->parseExpression($source);
	assert($assign instanceof AssignmentNode);
	$target = $assign->target;
	assert($target instanceof ListNode);
	$nested = $target->items->getItems()[1];
	printf(
		"%-26s %-12s %-10s %s\n",
		$source,
		shortClass($target),
		json_encode($target->listKeyword?->text),
		$nested instanceof ArrayItemNode ? shortClass($nested->value) : '',
	);
}

// the very same brackets on the other side of the assignment are a literal and stay an ArrayNode
$literal = $parser->parseExpression('$row = [$a, $b]');
assert($literal instanceof AssignmentNode);
printf("%-26s %-12s\n", '$row = [$a, $b]', shortClass($literal->expression));

// the spelling is in the slots, so the text comes back exactly as it was written
echo "\n";
foreach (['list($a, [$b]) = $row', '[$a, list($b)] = $row'] as $source) {
	printf("%-26s prints back as %s\n", $source, (string) $parser->parseExpression($source));
}
