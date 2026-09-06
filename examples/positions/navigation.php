<?php declare(strict_types=1);

/**
 * Walking the file token by token, and measuring a line the way a "line too long" rule must.
 *
 * Demonstrates: Token::getNext(), getPrevious(), is(), startsLine(), getLineWidth(), getLineIndentation()
 * Usage:        php examples/positions/navigation.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\Expression\ArrayNode;
use PhpSyntax\Parser;
use PhpSyntax\Style;
use PhpSyntax\Token;

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

// from any token to its neighbours, across nodes and across the whole file
$array = $file->find(ArrayNode::class)[0];
$open = $array->openDelimiter;
echo 'the token before "[" is ', json_encode(must($open->getPrevious())->text), "\n";
echo 'the token after "[" is ', json_encode(must($open->getNext())->text), "\n\n";

// counting something over the whole file is a plain loop, no traversal needed
$commas = $naive = 0;
foreach (walkTokens($file->getFirstToken()) as $token) {
	$commas += $token->is(',') ? 1 : 0;
	$naive += $token->text === ',' ? 1 : 0;
}

// the comma inside "{$driver},{$host}" is a token of its own, part of the string;
// is() knows that a text only matches punctuation and operators, a plain comparison does not
echo "commas by is(','): $commas\n";
echo "commas by comparing the text: $naive\n\n";

// line width, tabs in the indentation counted as the style says: this is what a line-length
// rule measures, and it is not the same as strlen()
$style = new Style(indent: "\t", tabWidth: 4);
foreach (walkTokens($file->getFirstToken()) as $token) {
	if ($token->startsLine() && $token->getLineIndentation() !== '') {
		printf(
			"line %d: width %2d, strlen %2d, indented with %s\n",
			$token->getLine(),
			$token->getLineWidth($style),
			strlen(rtrim(explode("\n", $code)[$token->getLine() - 1])),
			json_encode($token->getLineIndentation()),
		);
	}
}


/** @return Generator<Token> */
function walkTokens(?Token $first): Generator
{
	for ($token = $first; $token !== null; $token = $token->getNext()) {
		yield $token;
	}
}
