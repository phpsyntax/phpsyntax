<?php declare(strict_types=1);

use PhpSyntax\TokenKind;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// host tokens that never become a token: trivia, errors, and aliases of listed constants
$ignored = ['T_WHITESPACE', 'T_COMMENT', 'T_DOC_COMMENT', 'T_OPEN_TAG', 'T_BAD_CHARACTER', 'T_DOUBLE_COLON'];


test('every host T_* constant has a kind or is explicitly ignored', function () use ($ignored) {
	$byId = [];
	foreach (TokenKind::HostConstants as $name => $kind) {
		if (defined($name)) {
			$byId[constant($name)] = $kind;
		}
	}

	$missing = [];
	for ($id = 256; $id < 1000; $id++) {
		$name = token_name($id);
		if ($name !== 'UNKNOWN' && !isset($byId[$id]) && !in_array($name, $ignored, strict: true)) {
			$missing[] = $name;
		}
	}
	Assert::same([], $missing);
});


/** @return array<string, int> */
function getKinds(): array
{
	$constants = new ReflectionClass(TokenKind::class)->getConstants();
	unset($constants['HostConstants']);
	return $constants;
}


test('kinds are unique and leave the ordinals of single characters free', function () {
	$kinds = getKinds();
	Assert::same(count($kinds), count(array_unique($kinds)));
	foreach ($kinds as $name => $kind) {
		Assert::true($kind === TokenKind::EndOfFile || $kind > 255, $name);
	}
});


test('host constants map to existing kinds', function () {
	$kinds = array_flip(getKinds());
	foreach (TokenKind::HostConstants as $name => $kind) {
		Assert::match('T_%a%', $name);
		Assert::hasKey($kind, $kinds);
	}
});
