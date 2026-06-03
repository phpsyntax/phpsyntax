<?php declare(strict_types=1);

use PhpSyntax\{Node, Token, TokenKind, Trivia, TriviaKind};
use PhpSyntax\Nodes\{FileNode, NodeList, StatementNode};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


final class WordStatement extends StatementNode
{
	public function __construct(
		public Token $token { set => $this->token = $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	public function getChildren(): array
	{
		return [$this->token];
	}


	public function replaceChild(Node|Token $old, Node|Token $new): void
	{
	}
}


/** @param list<Trivia> $leading */
function word(string $text, array $leading = []): Token
{
	$token = new Token(TokenKind::Variable, $text);
	$token->setLeadingTrivia($leading);
	$token->setTrailingTrivia([new Trivia(TriviaKind::EndOfLine, "\n")]);
	return $token;
}


function statement(Token $token): StatementNode
{
	return new WordStatement($token);
}


test('a tree built by hand: order, navigation, lines and offsets follow the trivia', function () {
	$a = word('$a', [new Trivia(TriviaKind::OpenTag, "<?php\n"), new Trivia(TriviaKind::Whitespace, "\t")]);
	$b = word('$b', [new Trivia(TriviaKind::EndOfLine, "\n")]);
	$eof = new Token(TokenKind::EndOfFile, '');
	$file = new FileNode(new NodeList([statement($a), statement($b)]), $eof);
	Assert::same("<?php\n\t\$a\n\n\$b\n", (string) $file);

	Assert::same([$a, $b, $eof], $file->getIndex()->getTokens());
	Assert::same($b, $a->getNext());
	Assert::same($a, $b->getPrevious());
	Assert::null($a->getPrevious());
	Assert::null($eof->getNext());

	Assert::same([2, 2, 7], [$a->getLine(), $a->getColumn(), $a->getOffset()]);
	Assert::same([4, 1, 11], [$b->getLine(), $b->getColumn(), $b->getOffset()]);
	Assert::same(5, $eof->getLine());
	Assert::true($b->startsLine());
});


test('a change of text or trivia moves what follows, a structural change also the order', function () {
	$a = word('$a', [new Trivia(TriviaKind::OpenTag, "<?php\n")]);
	$b = word('$b');
	$eof = new Token(TokenKind::EndOfFile, '');
	$first = statement($a);
	$file = new FileNode(new NodeList([$first, statement($b)]), $eof);
	Assert::same(3, $b->getLine());
	Assert::same(0, $file->revision);

	$a->setText("\$aa\n");
	Assert::same(4, $b->getLine());
	Assert::same(11, $b->getOffset());
	$b->setLeadingTrivia([new Trivia(TriviaKind::EndOfLine, "\n")]);
	Assert::same(5, $b->getLine());
	Assert::same(6, $eof->getLine());
	Assert::same(2, $file->revision);

	$file->statements->removeItem($first);
	Assert::same(3, $file->revision);
	Assert::same([$b, $eof], $file->getIndex()->getTokens());
	Assert::same(2, $b->getLine());
	Assert::null($b->getPrevious());
	Assert::null($a->getLine());
	Assert::null($a->getNext());
	Assert::exception(fn() => $file->getIndex()->getIndex($a), InvalidArgumentException::class, 'The token does not belong to the indexed tree.');

	$file->statements->insert(0, $first);
	Assert::same([$a, $b, $eof], $file->getIndex()->getTokens());
	Assert::same(2, $a->getLine());
	Assert::same(5, $b->getLine());
});
