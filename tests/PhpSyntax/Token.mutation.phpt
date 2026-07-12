<?php declare(strict_types=1);

use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Parser;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;
use PhpSyntax\Trivia;
use PhpSyntax\TriviaKind;
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
	Assert::true($tokens[3]->isSemicolon());
	Assert::same(TokenKind::CloseTag, $tokens[3]->kind);
	Assert::true($tokens[4]->isOpenTagWithEcho());
	Assert::false($tokens[0]->isSemicolon());
	Assert::true($tokens[0]->is(TokenKind::Identifier, ';'));
	Assert::true($tokens[1]->is('('));
	Assert::false($tokens[1]->is(')', TokenKind::Variable));
});


test('setText and trivia setters record a non-structural mutation', function () {
	$file = parse('<?php $a;');
	[$a, $semicolon] = tokens($file);
	$a->setText('$b');
	$semicolon->setTrailingTrivia([new Trivia(TriviaKind::Whitespace, ' ')]);
	$a->setLeadingTrivia([new Trivia(TriviaKind::OpenTag, "<?php\n")]);
	Assert::same("<?php\n\$b; ", (string) $file);
	Assert::same(3, $file->revision);
	Assert::same(2, $a->getLine());
});


test('startsLine and indentation', function () {
	$file = parse("<?php\n\t\$a; \$b;\n\n  // c\n    \$c;");
	[$a, , $b, , $c] = tokens($file);
	Assert::true($a->startsLine());
	Assert::false($b->startsLine());
	Assert::true($c->startsLine());
	Assert::same("\t", $a->getIndentation());
	Assert::same('', $b->getIndentation());
	Assert::same('    ', $c->getIndentation());

	$c->setIndentation("\t\t");
	Assert::same("<?php\n\t\$a; \$b;\n\n  // c\n\t\t\$c;", (string) $file);
	$a->setIndentation('');
	Assert::same("<?php\n\$a; \$b;\n\n  // c\n\t\t\$c;", (string) $file);
	Assert::exception(fn() => $b->setIndentation("\t"), LogicException::class, "Token '\$b' does not start a line.");

	// the space after an inline comment is not indentation and survives reindenting
	$file = parse("<?php\n/*enum*/ final class A {}");
	[$final] = tokens($file);
	Assert::same('', $final->getIndentation());
	$final->setIndentation("\t");
	Assert::same("<?php\n\t/*enum*/ final class A {}", (string) $file);
});


test('ensureLeadingNewline and removeTrailingWhitespace', function () {
	$file = parse("<?php\n\$a;  \$b; // c  \n\$d;  ");
	[$a, $semicolonA, $b, $semicolonB, $d, $semicolonD] = tokens($file);
	Assert::same([2, 3], [$b->getLine(), $d->getLine()]);
	$b->ensureLeadingNewline();
	Assert::same("<?php\n\$a;\n\$b; // c  \n\$d;  ", (string) $file);
	Assert::same([3, 4], [$b->getLine(), $d->getLine()]); // the lines counted before follow the line ending
	$b->ensureLeadingNewline("\r\n");
	Assert::same("<?php\n\$a;\n\$b; // c  \n\$d;  ", (string) $file);

	$semicolonB->removeTrailingWhitespace();
	Assert::same("<?php\n\$a;\n\$b; // c\n\$d;  ", (string) $file);
	$semicolonD->removeTrailingWhitespace();
	Assert::same("<?php\n\$a;\n\$b; // c\n\$d;", (string) $file);
	Assert::same(3, $b->getLine());
});


test('setBlankLinesBefore keeps comments and the open tag', function () {
	$file = parse("<?php\n\n\n// c\n\$a;\n\$b;");
	[$a, , $b] = tokens($file);
	$a->setBlankLinesBefore(1);
	Assert::same("<?php\n\n// c\n\$a;\n\$b;", (string) $file);
	$a->setBlankLinesBefore(0);
	Assert::same("<?php\n// c\n\$a;\n\$b;", (string) $file);
	$b->setBlankLinesBefore(2, "\r\n");
	Assert::same("<?php\n// c\n\$a;\n\r\n\r\n\$b;", (string) $file);
	Assert::same(6, $b->getLine());
});


test('whitespace inside string interpolation is refused', function () {
	$file = parse('<?php "{$a }";');
	$a = tokens($file)[2];
	Assert::same('$a', $a->text);
	Assert::exception(fn() => $a->removeTrailingWhitespace(), LogicException::class, "Token '\$a' is inside string interpolation; its whitespace cannot be changed.");
	$a->setTrailingTrivia([]);
	Assert::same('<?php "{$a}";', (string) $file);
});
