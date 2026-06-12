<?php declare(strict_types=1);

use PhpSyntax\Nodes\FileNode;
use PhpSyntax\{Parser, Token, Trivia};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function parse(string $code): FileNode
{
	return (new Parser)->parse($code);
}


/** @return list<Token> */
function tokens(FileNode $file): array
{
	return $file->getIndex()->getTokens();
}


test('kind queries', function () {
	$tokens = tokens(parse("<?php foo() ?>\n<?= 1;"));
	Assert::same(Token::CloseTag, $tokens[3]->id);
	Assert::same(Token::OpenTagWithEcho, $tokens[4]->id);
	Assert::true($tokens[0]->is([Token::Identifier, ';']));
	Assert::true($tokens[1]->is('('));
	Assert::false($tokens[1]->is([')', Token::Variable]));
});


test('setText and trivia setters record a non-structural mutation', function () {
	$file = parse('<?php $a;');
	[$a, $semicolon] = tokens($file);
	$a->setText('$b');
	$semicolon->setTrailingTrivia([new Trivia(Trivia::Whitespace, ' ')]);
	$a->setLeadingTrivia([new Trivia(Trivia::OpenTag, "<?php\n")]);
	Assert::same("<?php\n\$b; ", (string) $file);
	Assert::same(3, $file->revision);
	Assert::same(2, $a->currentLine);
});


test('ensureStartsLine and removeTrailingWhitespace', function () {
	$file = parse("<?php\n\$a;  \$b; // c  \n\$d;  ");
	[$a, $semicolonA, $b, $semicolonB, $d, $semicolonD] = tokens($file);
	Assert::same([2, 3], [$b->currentLine, $d->currentLine]);
	$b->ensureStartsLine("\n");
	Assert::same("<?php\n\$a;\n\$b; // c  \n\$d;  ", (string) $file);
	Assert::same(Trivia::LineEnding, $semicolonA->trailingTrivia[count($semicolonA->trailingTrivia) - 1]->id); // where the lexer would put it
	Assert::same([], $b->leadingTrivia);
	Assert::same([3, 4], [$b->currentLine, $d->currentLine]); // the lines counted before follow the line ending
	$b->ensureStartsLine("\r\n");
	Assert::same("<?php\n\$a;\n\$b; // c  \n\$d;  ", (string) $file);

	$semicolonB->removeTrailingWhitespace();
	Assert::same("<?php\n\$a;\n\$b; // c\n\$d;  ", (string) $file);
	$semicolonD->removeTrailingWhitespace();
	Assert::same("<?php\n\$a;\n\$b; // c\n\$d;", (string) $file);
	Assert::same(3, $b->currentLine);
});


test('setBlankLinesBefore keeps comments and the open tag', function () {
	$file = parse("<?php\n\n\n// c\n\$a;\n\$b;");
	[$a, , $b] = tokens($file);
	Assert::same(2, $a->countBlankLinesBefore());
	Assert::same(0, $b->countBlankLinesBefore());
	$a->setBlankLinesBefore(1, "\n");
	Assert::same("<?php\n\n// c\n\$a;\n\$b;", (string) $file);
	Assert::same(1, $a->countBlankLinesBefore());
	$a->setBlankLinesBefore(0, "\n");
	Assert::same("<?php\n// c\n\$a;\n\$b;", (string) $file);
	Assert::same(0, $a->countBlankLinesBefore());
	$b->setBlankLinesBefore(2, "\r\n");
	Assert::same("<?php\n// c\n\$a;\n\r\n\r\n\$b;", (string) $file);
	Assert::same(2, $b->countBlankLinesBefore());
	Assert::same(6, $b->currentLine);
});


test('whitespace inside string interpolation is refused', function () {
	$file = parse('<?php "{$a }";');
	$a = tokens($file)[2];
	Assert::same('$a', $a->text);
	Assert::exception($a->removeTrailingWhitespace(...), LogicException::class, "Token `\$a` is inside string interpolation; its whitespace cannot be changed.");
	Assert::exception(fn() => $a->setTrailingSpace(''), LogicException::class, "Token `\$a` is inside string interpolation; its whitespace cannot be changed.");
	$a->setTrailingTrivia([]);
	Assert::same('<?php "{$a}";', (string) $file);
});


test('the whitespace helpers refuse what is no whitespace, line ending or count before they write', function () {
	$code = "<?php\nfoo(); bar();\n";
	$file = parse($code);
	[$foo, , , $semicolon, $bar] = tokens($file);
	Assert::exception(fn() => $foo->setTrailingSpace('evil'), InvalidArgumentException::class, '`evil` is not whitespace within a line, which is made of spaces and tabs.');
	Assert::exception(fn() => $semicolon->setTrailingSpace("\n"), InvalidArgumentException::class, '`\n` is not whitespace within a line, which is made of spaces and tabs.');
	Assert::exception(fn() => $foo->setIndentation('//'), InvalidArgumentException::class, '`//` is not whitespace within a line, which is made of spaces and tabs.');
	Assert::exception(fn() => $bar->ensureStartsLine('<br>'), InvalidArgumentException::class, '`<br>` is not a line ending, which is `\n`, `\r\n` or `\r`.');
	Assert::exception(fn() => $foo->setBlankLinesBefore(1, "\n\n"), InvalidArgumentException::class, '`\n\n` is not a line ending, which is `\n`, `\r\n` or `\r`.');
	Assert::exception(fn() => $foo->setBlankLinesBefore(-1, "\n"), InvalidArgumentException::class, 'Count of blank lines `-1` is negative.');
	Assert::same($code, (string) $file);
	Assert::same(0, $file->revision);
});
