<?php declare(strict_types=1);

use PhpSyntax\Helpers;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('formatCode() writes a code span of Markdown, longer than any run of backticks inside', function () {
	Assert::same('`$a`', Helpers::formatCode('$a'));
	Assert::same('``$a = `ls`;``', Helpers::formatCode('$a = `ls`;'));
	Assert::same('`` ` ``', Helpers::formatCode('`')); // padded where the code starts or ends with a backtick
	Assert::same('``` a``b ```', Helpers::formatCode(' a``b '));
	Assert::same("`''`", Helpers::formatCode('')); // Markdown cannot mark empty code
});


test('escapeString() and unescapeString() are inverse for a double-quoted string', function () {
	$value = "a\"b\$c\\d\n\x01";
	Assert::same('a\"b\$c\\\\d\n\x01', Helpers::escapeString($value, quote: '"'));
	Assert::same($value, Helpers::unescapeString(Helpers::escapeString($value, quote: '"'), quote: '"'));
});
