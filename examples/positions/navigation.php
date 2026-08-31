<?php declare(strict_types=1);

/**
 * Walking the file token by token, and measuring a line the way a "line too long" rule must.
 *
 * Demonstrates: `Token::getNext()`, `getPrevious()`, `is()`, `startsLine()`, `getLineIndentation()`, `Indentation::measureLineWidth()`, `Node::getTokens()`
 * Usage:        php examples/positions/navigation.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Indentation;
use PhpSyntax\Nodes\Expression\ArrayNode;
use PhpSyntax\Parser;
use PhpSyntax\Style;

$code = sample(<<<'PHP'
	<?php
	class Config
	{
		public function dsn(string $driver, string $host): string
		{
			$parts = ['driver' => $driver, 'host' => $host];
			$label = "{$driver},{$host}";
			return implode(',', $parts) . $label;
		}
	}

	PHP);

$file = new Parser()->parse($code);

// from any token to its neighbors, across nodes and across the whole file
$array = $file->findFirst(ArrayNode::class);
$open = $array->openDelimiter;
echo 'the token before "[" is ', json_encode($open->getPrevious()->text), "\n";
echo 'the token after "[" is ', json_encode($open->getNext()->text), "\n\n";

// counting something over the whole file is a plain loop, no traversal needed
$byKind = $byText = 0;
foreach ($file->getTokens() as $token) {
	$byKind += $token->is(ord(',')) ? 1 : 0;
	$byText += $token->is(',') ? 1 : 0;
}

// the comma inside "{$driver},{$host}" is a token of its own: its text is a comma, its kind is string content
echo "commas by kind, is(ord(',')): $byKind\n";
echo "commas by text, is(','): $byText\n\n";

// line width, every tab counted to the next tab stop of the style: this is what a line-length
// rule measures, and it is not the same as strlen()
$style = new Style(indent: "\t", tabWidth: 4);
foreach ($file->getTokens() as $token) {
	if ($token->startsLine() && $token->getLineIndentation() !== '') {
		printf(
			"line %d: width %2d, strlen %2d, indented with %s\n",
			$token->currentLine,
			Indentation::measureLineWidth($token, $style),
			strlen(rtrim(explode("\n", $code)[$token->currentLine - 1])),
			json_encode($token->getLineIndentation()),
		);
	}
}
