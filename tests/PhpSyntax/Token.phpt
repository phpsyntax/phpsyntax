<?php declare(strict_types=1);

use PhpSyntax\Token;
use PhpSyntax\TokenKind;
use PhpSyntax\Trivia;
use PhpSyntax\TriviaKind;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('token from the lexer carries its original position', function () {
	$token = new Token(TokenKind::Variable, '$a', originalOffset: 6, originalLine: 1);
	Assert::same(TokenKind::Variable, $token->kind);
	Assert::same('$a', $token->text);
	Assert::same(6, $token->originalOffset);
	Assert::same(1, $token->originalLine);
	Assert::same([], $token->leadingTrivia);
	Assert::same([], $token->trailingTrivia);
});


test('synthetic token has no original position', function () {
	$token = new Token(ord(';'), ';');
	Assert::null($token->originalOffset);
	Assert::null($token->originalLine);
});


test('string form is leading trivia, text and trailing trivia', function () {
	$token = new Token(TokenKind::Return, 'return');
	$token->setLeadingTrivia([
		new Trivia(TriviaKind::EndOfLine, "\n"),
		new Trivia(TriviaKind::Whitespace, "\t"),
	]);
	$token->setTrailingTrivia([
		new Trivia(TriviaKind::Whitespace, ' '),
		new Trivia(TriviaKind::Comment, '// done'),
	]);
	Assert::same("\n\treturn // done", (string) $token);
});


test('trivia is a value object', function () {
	$trivia = new Trivia(TriviaKind::Comment, '/* c */', inInterpolation: true);
	Assert::same(TriviaKind::Comment, $trivia->kind);
	Assert::same('/* c */', $trivia->text);
	Assert::true($trivia->inInterpolation);
	Assert::false(new Trivia(TriviaKind::Whitespace, ' ')->inInterpolation);
});
