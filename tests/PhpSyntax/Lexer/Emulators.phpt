<?php declare(strict_types=1);

/**
 * Emulators over hand-built raw token streams, as an older PHP tokenizer would produce them.
 */

use PhpSyntax\Lexer\Emulators\PipeOperator;
use PhpSyntax\Lexer\Emulators\VoidCast;
use PhpSyntax\Lexer\Lexer;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


/**
 * Builds raw tokens from pairs [kind, text], with the offset following the texts.
 * @param  list<array{int, string}>  $pairs
 * @return list<Token>
 */
function raw(array $pairs): array
{
	$tokens = [];
	$offset = 0;
	foreach ($pairs as [$kind, $text]) {
		$tokens[] = new Token($kind, $text, $offset, 1);
		$offset += strlen($text);
	}

	return $tokens;
}


/**
 * @param  list<Token>  $tokens
 * @return list<array{int, string, ?int}>
 */
function summarize(array $tokens): array
{
	return array_map(fn(Token $t) => [$t->kind, $t->text, $t->originalOffset], $tokens);
}


test('PipeOperator: adjacent | and >', function () {
	$emulator = new PipeOperator;
	Assert::true($emulator->isNeeded('$a |> f(...)'));
	Assert::false($emulator->isNeeded('$a | $b > $c'));

	$tokens = $emulator->emulate(raw([
		[TokenKind::Variable, '$a'],
		[ord('|'), '|'],
		[ord('>'), '>'],
		[TokenKind::Variable, '$b'],
		[ord('|'), '|'],
		[TokenKind::Whitespace, ' '],
		[ord('>'), '>'],
		[ord('|'), '|'],
	]));
	Assert::same([
		[TokenKind::Variable, '$a', 0],
		[TokenKind::Pipe, '|>', 2],
		[TokenKind::Variable, '$b', 4],
		[ord('|'), '|', 6],
		[TokenKind::Whitespace, ' ', 7],
		[ord('>'), '>', 8],
		[ord('|'), '|', 9],
	], summarize($tokens));
});


test('VoidCast: (void) with optional spaces and tabs inside', function () {
	$emulator = new VoidCast;
	Assert::true($emulator->isNeeded("( \tVOID )"));
	Assert::false($emulator->isNeeded("(\nvoid)"));

	$tokens = $emulator->emulate(raw([
		[ord('('), '('],
		[TokenKind::Whitespace, " \t"],
		[TokenKind::Identifier, 'Void'],
		[TokenKind::Whitespace, ' '],
		[ord(')'), ')'],
		[ord('('), '('],
		[TokenKind::Identifier, 'void'],
		[ord(')'), ')'],
		[ord('('), '('],
		[TokenKind::Whitespace, "\n"],
		[TokenKind::Identifier, 'void'],
		[ord(')'), ')'],
		[ord('('), '('],
		[TokenKind::Identifier, 'void'],
		[TokenKind::Identifier, 'x'],
		[ord(')'), ')'],
		[ord('('), '('],
	]));
	Assert::same([
		[TokenKind::VoidCast, "( \tVoid )", 0],
		[TokenKind::VoidCast, '(void)', 9],
		[ord('('), '(', 15],
		[TokenKind::Whitespace, "\n", 16],
		[TokenKind::Identifier, 'void', 17],
		[ord(')'), ')', 21],
		[ord('('), '(', 22],
		[TokenKind::Identifier, 'void', 23],
		[TokenKind::Identifier, 'x', 27],
		[ord(')'), ')', 28],
		[ord('('), '(', 29],
	], summarize($tokens));
});


test('host emulators follow the PHP version', function () {
	$classes = array_map(get_class(...), Lexer::createHostEmulators());
	$expected = [];
	if (PHP_VERSION_ID < 80500) {
		$expected = [...$expected, PipeOperator::class, VoidCast::class];
	}
	Assert::same($expected, $classes);
});
