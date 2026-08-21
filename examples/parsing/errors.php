<?php declare(strict_types=1);

/**
 * What happens when the code is not valid PHP: a ParseException that says where in the source it is.
 *
 * Demonstrates: ParseException, $sourceLine, $sourceColumn, $sourceOffset
 * Usage:        php examples/parsing/errors.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\ParseException;
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	function total(array $items)
	{
		return array_sum($items;
	}
	PHP);

try {
	new Parser()->parse($code);

} catch (ParseException $e) {
	echo $e->getMessage(), "\n";
	// the line and the column are where the reader looks, the offset is where an editor jumps
	echo "line $e->sourceLine, column $e->sourceColumn, offset $e->sourceOffset\n";

	$line = explode("\n", $code)[$e->sourceLine - 1];
	echo $line, "\n";
	// the indentation of the line is kept, so that the caret lands under the character whatever it is made of
	echo preg_replace('~\S~', ' ', mb_substr($line, 0, $e->sourceColumn - 1)), "^\n";
}
