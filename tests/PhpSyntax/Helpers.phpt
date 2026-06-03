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
