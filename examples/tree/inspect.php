<?php declare(strict_types=1);

/**
 * The shape of the tree: nodes, named slots, tokens, and the trivia hanging off them.
 *
 * Demonstrates: Node::getChildren(), Node::Slots, Token::$text, Token::$leadingTrivia
 * Usage:        php examples/tree/inspect.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Node;
use PhpSyntax\Parser;
use PhpSyntax\Token;
use PhpSyntax\Trivia;

$code = sample(<<<'PHP'
	<?php
	// a discount that applies from ten pieces
	if ($qty >= 10) {
		$price *= 0.9;
	}
	PHP);

$file = new Parser()->parse($code);
dumpTree($file, '');


function dumpTree(Node|Token $node, string $indent): void
{
	if ($node instanceof Token) {
		echo describeToken($node), "\n";
		return;
	}

	echo $node::class, "\n";
	foreach ($node::Slots as $slot) {
		if ($node->$slot !== null) {
			echo $indent, '  ', $slot, ': ';
			dumpTree($node->$slot, $indent . '  ');
		}
	}

	// a list has no slots and reads its items itself
	if ($node::Slots === []) {
		foreach ($node->getChildren() as $child) {
			echo $indent, '  - ';
			dumpTree($child, $indent . '  ');
		}
	}
}


function describeToken(Token $token): string
{
	$out = "Token '$token->text'";
	foreach (['leadingTrivia' => 'leading', 'trailingTrivia' => 'trailing'] as $property => $label) {
		if ($token->$property) {
			$out .= '  ' . $label . ': ' . implode(' ', array_map(
				fn(Trivia $trivia) => $trivia->kind->name . '(' . json_encode($trivia->text, JSON_UNESCAPED_SLASHES) . ')',
				$token->$property,
			));
		}
	}

	return $out;
}
