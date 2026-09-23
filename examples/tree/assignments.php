<?php declare(strict_types=1);

/**
 * Three ways of assigning are three classes, so a rule tells them apart by type instead of reading
 * the text of the operator, and the slot of the target says which of them takes a destructuring.
 *
 * Demonstrates: AssignmentNode, CombinedAssignmentNode, AssignmentByReferenceNode
 * Usage:        php examples/tree/assignments.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\Expression\AssignmentByReferenceNode;
use PhpSyntax\Nodes\Expression\AssignmentNode;
use PhpSyntax\Nodes\Expression\CombinedAssignmentNode;
use PhpSyntax\Parser;

$parser = new Parser;

printf("%-18s %-27s %-14s %s\n", 'written', 'node', 'target', 'operator');

foreach ([
	'$a = 1',
	'$a += 1',
	'$a ??= 1',
	'$a .= 1',
	'$a = &$b',
	'[$a, $b] = $x',
	'list($a, $b) = $x',
] as $source) {
	$assign = $parser->parseExpression($source);
	[$target, $operator] = match (true) {
		$assign instanceof AssignmentNode,
		$assign instanceof CombinedAssignmentNode => [$assign->target, $assign->operator->text],
		$assign instanceof AssignmentByReferenceNode => [$assign->target, $assign->equals->text . $assign->ampersand->text],
		default => throw new LogicException('The example expected an assignment here.'),
	};
	printf(
		"%-18s %-27s %-14s %s\n",
		$source,
		shortClass($assign),
		shortClass($target),
		$operator,
	);
}

// only the plain assignment takes a destructuring, and its slot says so with a wider type; the
// grammar takes a variable alone on the left of the other two, so this is not PHP
try {
	$parser->parseExpression('[$a, $b] += 1');
} catch (PhpSyntax\ParseException $e) {
	echo "\n", $e->getMessage(), "\n";
}
