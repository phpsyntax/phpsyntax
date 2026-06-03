<?php declare(strict_types=1);

/**
 * Emulators over hand-built raw token streams, as an older PHP tokenizer would produce them.
 */

use PhpSyntax\{Emulator, Lexer, Token};
use PhpSyntax\Emulators\{PipeOperator, VoidCast};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


/**
 * Builds raw tokens from pairs [host id, text], with the offset following the texts.
 * @param  list<array{int, string}>  $pairs
 * @return array<int, Token>
 */
function raw(array $pairs): array
{
	$tokens = [];
	$offset = 0;
	foreach ($pairs as [$kind, $text]) {
		$tokens[] = new Token($kind, $text, 1, $offset);
		$offset += strlen($text);
	}

	return $tokens;
}


/**
 * @param  array<int, Token>  $tokens
 * @return list<array{int, string, int}>
 */
function summarize(array $tokens): array
{
	return array_values(array_map(fn(Token $t) => [$t->id, $t->text, $t->pos], $tokens));
}


test('PipeOperator: adjacent | and >', function () {
	$emulator = new PipeOperator;
	Assert::true($emulator->isNeeded('$a |> f(...)'));
	Assert::false($emulator->isNeeded('$a | $b > $c'));

	$tokens = $emulator->emulate(raw([
		[T_VARIABLE, '$a'],
		[ord('|'), '|'],
		[ord('>'), '>'],
		[T_VARIABLE, '$b'],
		[ord('|'), '|'],
		[T_WHITESPACE, ' '],
		[ord('>'), '>'],
		[ord('|'), '|'],
	]));
	Assert::same([
		[T_VARIABLE, '$a', 0],
		[Token::Pipe, '|>', 2],
		[T_VARIABLE, '$b', 4],
		[ord('|'), '|', 6],
		[T_WHITESPACE, ' ', 7],
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
		[T_WHITESPACE, " \t"],
		[T_STRING, 'Void'],
		[T_WHITESPACE, ' '],
		[ord(')'), ')'],
		[ord('('), '('],
		[T_STRING, 'void'],
		[ord(')'), ')'],
		[ord('('), '('],
		[T_WHITESPACE, "\n"],
		[T_STRING, 'void'],
		[ord(')'), ')'],
		[ord('('), '('],
		[T_STRING, 'void'],
		[T_STRING, 'x'],
		[ord(')'), ')'],
		[ord('('), '('],
	]));
	Assert::same([
		[Token::VoidCast, "( \tVoid )", 0],
		[Token::VoidCast, '(void)', 9],
		[ord('('), '(', 15],
		[T_WHITESPACE, "\n", 16],
		[T_STRING, 'void', 17],
		[ord(')'), ')', 21],
		[ord('('), '(', 22],
		[T_STRING, 'void', 23],
		[T_STRING, 'x', 27],
		[ord(')'), ')', 28],
		[ord('('), '(', 29],
	], summarize($tokens));
});


test('the lexer takes a token of an emulator by its kind', function () {
	// on any PHP: the pipe, whole from the host or merged by PipeOperator, becomes a token of the emulator
	$emulator = new class implements Emulator {
		public function isNeeded(string $code): bool
		{
			return true;
		}


		public function emulate(array $tokens): array
		{
			return array_map(
				fn(Token $t) => $t->text === '|>' ? new Token(Token::Pipe, '|>', $t->line, $t->pos) : $t,
				(new PipeOperator)->emulate($tokens),
			);
		}
	};
	$describe = fn(Token $t) => [$t->id, $t->text, $t->pos];
	$code = "<?php \$a |> f(...);\n";
	Assert::same(
		array_map($describe, (new Lexer)->tokenize($code)),
		array_map($describe, new Lexer([$emulator])->tokenize($code)),
	);
	Assert::same([Token::Pipe, '|>', 9], $describe(new Lexer([$emulator])->tokenize($code)[1]));
});


test('host emulators follow the PHP version', function () {
	$classes = array_map(get_class(...), Lexer::createHostEmulators());
	$expected = [];
	if (PHP_VERSION_ID < 80500) {
		$expected = [...$expected, PipeOperator::class, VoidCast::class];
	}
	Assert::same($expected, $classes);
});
