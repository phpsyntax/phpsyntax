<?php declare(strict_types=1);

use PhpSyntax\Style;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('Style defaults, detected line ending and derived styles', function () {
	$style = new Style;
	Assert::same(["\t", "\n", 4], [$style->indent, $style->eol, $style->tabWidth]);
	Assert::same("\t\t", $style->indent(2));

	Assert::same("\n", Style::detectEol(''));
	Assert::same("\n", Style::detectEol("a\nb\r\n"));
	Assert::same("\r\n", Style::detectEol("a\r\nb\r\nc\n"));

	$windows = $style->withEol("\r\n")->withIndent('    ');
	Assert::same(["\r\n", '    ', "\t"], [$windows->eol, $windows->indent, $style->indent]);
});
