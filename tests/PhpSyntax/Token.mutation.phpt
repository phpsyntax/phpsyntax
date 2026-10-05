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
	Assert::same(2, $a->getCurrentLine());
});


test('replaceWith puts a token of another kind in place, the trivia around staying', function () {
	$file = parse('<?php class A { /* c */ protected function f() {} }');
	$protected = array_find(tokens($file), fn(Token $token) => $token->is(Token::Protected)) ?? throw new LogicException;
	$public = new Token(Token::Public, 'public');
	$protected->replaceWith($public);
	Assert::same('<?php class A { /* c */ public function f() {} }', (string) $file);
	Assert::true($public->is(Token::Public));
	Assert::null($protected->parent);
	Assert::same([], $protected->leadingTrivia);

	// a token the neighbor would be read together with is kept apart from it
	$file = parse('<?php $a+1;');
	$plus = tokens($file)[1];
	$plus->replaceWith(new Token(ord('.'), '.'));
	Assert::same('<?php $a. 1;', (string) $file); // .1 would be a number

	// a separator of a list, and a refusal that moves nothing
	$file = parse('<?php f($a, $b);');
	$comma = array_find(tokens($file), fn(Token $token) => $token->is(',')) ?? throw new LogicException;
	assertRefused(fn() => $comma->replaceWith($comma->getNext() ?? throw new LogicException), LogicException::class, null, $file);
	Assert::exception(fn() => new Token(ord(';'), ';')->replaceWith(new Token(ord(','), ',')), LogicException::class, 'A token without a parent cannot be replaced.');
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
	assertRefused(fn() => $b->setIndentation("\t"), LogicException::class, "Token `\$b` does not start a line, so its indentation cannot be set.", $file);

	// the space after an inline comment is not indentation and survives reindenting
	$file = parse("<?php\n/*enum*/ final class A {}");
	[$final] = tokens($file);
	Assert::same('', $final->getIndentation());
	$final->setIndentation("\t");
	Assert::same("<?php\n\t/*enum*/ final class A {}", (string) $file);
});


test('isFollowedByLineEnding() sees a single line ending and nothing but whitespace up to the next token', function () {
	[$a, $plus, $b, $dot, $c, $and, $d, $semicolon] = tokens(parse("<?php \$a +\n\t\$b .\n\n\t\$c && // x\n\t\$d;"));
	Assert::true($plus->isFollowedByLineEnding());
	Assert::false($a->isFollowedByLineEnding());
	Assert::false($dot->isFollowedByLineEnding()); // a blank line is two line endings
	Assert::false($and->isFollowedByLineEnding()); // a comment stands between
	Assert::false($semicolon->isFollowedByLineEnding()); // nothing follows

	// a close tag ends its line by itself, and a token without a file has nothing after it
	[, $close] = tokens(parse("<?php \$a ?>\nhtml"));
	Assert::same(Token::CloseTag, $close->id);
	Assert::true($close->isFollowedByLineEnding());
	Assert::false(new Token(ord('+'), '+')->isFollowedByLineEnding());
});


test('the line indentation of a line a heredoc closes on is written in its closing marker', function () {
	$file = parse("<?php\n\t\$a = [<<<A\n\t\tx\n\t\tA, 1];\n");
	$one = array_find(tokens($file), fn(Token $token) => $token->text === '1') ?? throw new LogicException;
	Assert::same('', $one->getIndentation());
	Assert::same("\t\t", $one->getLineIndentation());
	Assert::same("\t", tokens($file)[0]->getLineIndentation());
});


test('ensureStartsLine and removeTrailingWhitespace', function () {
	$file = parse("<?php\n\$a;  \$b; // c  \n\$d;  ");
	[$a, $semicolonA, $b, $semicolonB, $d, $semicolonD] = tokens($file);
	Assert::same([2, 3], [$b->getCurrentLine(), $d->getCurrentLine()]);
	$b->ensureStartsLine("\n");
	Assert::same("<?php\n\$a;\n\$b; // c  \n\$d;  ", (string) $file);
	Assert::same(Trivia::LineEnding, $semicolonA->trailingTrivia[count($semicolonA->trailingTrivia) - 1]->id); // where the lexer would put it
	Assert::same([], $b->leadingTrivia);
	Assert::same([3, 4], [$b->getCurrentLine(), $d->getCurrentLine()]); // the lines counted before follow the line ending
	$b->ensureStartsLine("\r\n");
	Assert::same("<?php\n\$a;\n\$b; // c  \n\$d;  ", (string) $file);

	$semicolonB->removeTrailingWhitespace();
	Assert::same("<?php\n\$a;\n\$b; // c\n\$d;  ", (string) $file);
	$semicolonD->removeTrailingWhitespace();
	Assert::same("<?php\n\$a;\n\$b; // c\n\$d;", (string) $file);
	Assert::same(3, $b->getCurrentLine());
});


test('removeTrivia tidies the line around a comment', function () {
	$file = parse("<?php\n// a\n\$a;  // b\n\t// c\n\$d = /* i */ 1;\n");
	[$a, $semicolonA, $d, $equals] = tokens($file);

	$a->removeTrivia($a->leadingTrivia[1]); // its own line after the open tag
	Assert::same("<?php\n\$a;  // b\n\t// c\n\$d = /* i */ 1;\n", (string) $file);

	$semicolonA->removeTrivia($semicolonA->trailingTrivia[1]); // after code, takes the space before
	Assert::same("<?php\n\$a;\n\t// c\n\$d = /* i */ 1;\n", (string) $file);

	$d->removeTrivia($d->leadingTrivia[1]); // alone on its indented line
	Assert::same("<?php\n\$a;\n\$d = /* i */ 1;\n", (string) $file);

	$equals->removeTrivia($equals->trailingTrivia[1]); // inline, takes one adjacent space
	Assert::same("<?php\n\$a;\n\$d = 1;\n", (string) $file);

	$file = parse("<?php\n\t/* i */ \$e;\n");
	[$e] = tokens($file);
	$e->removeTrivia($e->leadingTrivia[2]); // inline at the start of a line, the indentation stays
	Assert::same("<?php\n\t\$e;\n", (string) $file);

	assertRefused(
		fn() => $d->removeTrivia(new Trivia(Trivia::Comment, '// x')),
		LogicException::class,
		null,
		$file,
	);
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
	Assert::same(6, $b->getCurrentLine());
});


test('whitespace inside string interpolation is refused', function () {
	$file = parse('<?php "{$a }";');
	$a = tokens($file)[2];
	Assert::same('$a', $a->text);
	assertRefused($a->removeTrailingWhitespace(...), LogicException::class, "Token `\$a` is inside string interpolation; its whitespace cannot be changed.", $file);
	assertRefused(fn() => $a->setTrailingSpace(''), LogicException::class, "Token `\$a` is inside string interpolation; its whitespace cannot be changed.", $file);
	$a->setTrailingTrivia([]);
	Assert::same('<?php "{$a}";', (string) $file);
});


test('the whitespace helpers refuse what is no whitespace, line ending or count before they write', function () {
	$code = "<?php\nfoo(); bar();\n";
	$file = parse($code);
	[$foo, , , $semicolon, $bar] = tokens($file);
	assertRefused(fn() => $foo->setTrailingSpace('evil'), InvalidArgumentException::class, '`evil` is not whitespace within a line, which is made of spaces and tabs.', $file);
	assertRefused(fn() => $semicolon->setTrailingSpace("\n"), InvalidArgumentException::class, '`\n` is not whitespace within a line, which is made of spaces and tabs.', $file);
	assertRefused(fn() => $foo->setIndentation('//'), InvalidArgumentException::class, '`//` is not whitespace within a line, which is made of spaces and tabs.', $file);
	assertRefused(fn() => $bar->ensureStartsLine('<br>'), InvalidArgumentException::class, '`<br>` is not a line ending, which is `\n`, `\r\n` or `\r`.', $file);
	assertRefused(fn() => $foo->setBlankLinesBefore(1, "\n\n"), InvalidArgumentException::class, '`\n\n` is not a line ending, which is `\n`, `\r\n` or `\r`.', $file);
	assertRefused(fn() => $foo->setBlankLinesBefore(-1, "\n"), InvalidArgumentException::class, 'Count of blank lines `-1` is negative.', $file);
	assertRefused(fn() => PhpSyntax\Indentation::set($foo, "\t", 'x'), InvalidArgumentException::class, '`x` is not whitespace within a line, which is made of spaces and tabs.', $file);
	Assert::same(0, $file->revision);
});
