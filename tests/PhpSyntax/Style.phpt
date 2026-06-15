<?php declare(strict_types=1);

use PhpSyntax\Style;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('Style defaults, detected line ending and derived styles', function () {
	$style = new Style;
	Assert::same(["\t", "\n", 4], [$style->indent, $style->lineEnding, $style->tabWidth]);
	Assert::same("\t\t", $style->indent(2));

	Assert::same("\n", Style::detectLineEnding(''));
	Assert::same("\n", Style::detectLineEnding("a\nb\r\n"));
	Assert::same("\r\n", Style::detectLineEnding("a\r\nb\r\nc\n"));
	Assert::same("\r", Style::detectLineEnding("a\rb\rc\n"));
	Assert::same("\n", Style::detectLineEnding("a\rb\n"));

	$windows = $style->withLineEnding("\r\n")->withIndent('    ');
	Assert::same(["\r\n", '    ', "\t"], [$windows->lineEnding, $windows->indent, $style->indent]);
});


test('Style refuses what is no indentation unit, line ending or tab width', function () {
	Assert::exception(fn() => new Style(indent: ''), InvalidArgumentException::class, "`''` is not an indentation unit, which is made of spaces and tabs.");
	Assert::exception(fn() => new Style(indent: ' x'), InvalidArgumentException::class, '` x` is not an indentation unit, which is made of spaces and tabs.');
	Assert::exception(fn() => new Style(lineEnding: "\n\n"), InvalidArgumentException::class, '`\n\n` is not a line ending, which is `\n`, `\r\n` or `\r`.');
	Assert::exception(fn() => (new Style)->withLineEnding(' '), InvalidArgumentException::class, '` ` is not a line ending, which is `\n`, `\r\n` or `\r`.');
	Assert::exception(fn() => new Style(tabWidth: 0), InvalidArgumentException::class, 'Tab width `0` is not positive.');
	Assert::same(" \t", new Style(indent: " \t")->indent);
});
