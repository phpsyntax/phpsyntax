<?php declare(strict_types=1);

use PhpSyntax\{Token, Trivia};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


/** @return array<string, int> */
function getKinds(): array
{
	return array_filter(new ReflectionClass(Token::class)->getConstants(), is_int(...));
}


test('every host token has a kind of a token or of a trivia, but for the lexer error', function () {
	$kinds = array_flip([...getKinds(), ...new ReflectionClass(Trivia::class)->getConstants()]);
	$missing = [];
	for ($id = 256; $id < 1000; $id++) {
		$name = token_name($id);
		if ($name !== 'UNKNOWN' && $name !== 'T_BAD_CHARACTER' && !isset($kinds[$id])) {
			$missing[] = $name;
		}
	}

	Assert::same([], $missing);
});


test('kinds are unique and leave the ordinals of single characters free', function () {
	$kinds = getKinds();
	Assert::same(count($kinds), count(array_unique($kinds)));
	foreach ($kinds as $name => $kind) {
		Assert::true($kind <= 0 || $kind > 255, $name);
	}
});


test('a token the running PHP does not tokenize yet has a negative kind of its own', function () {
	Assert::same(PHP_VERSION_ID >= 80500 ? T_PIPE : -2, Token::Pipe);
	Assert::same(PHP_VERSION_ID >= 80500 ? T_VOID_CAST : -1, Token::VoidCast);
});
