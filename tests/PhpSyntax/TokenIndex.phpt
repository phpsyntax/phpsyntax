<?php declare(strict_types=1);

use PhpSyntax\Nodes\{FileNode, PlainNodeList, StatementNode};
use PhpSyntax\{Token, Trivia};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


final class WordStatement extends StatementNode
{
	public const Slots = ['token'];


	public function __construct(
		public Token $token { set => $this->token = $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}


/** @param list<Trivia> $leading */
function word(string $text, array $leading = []): Token
{
	$token = new Token(Token::Variable, $text);
	$token->setLeadingTrivia($leading);
	$token->setTrailingTrivia([new Trivia(Trivia::LineEnding, "\n")]);
	return $token;
}


function statement(Token $token): StatementNode
{
	return new WordStatement($token);
}


test('a tree built by hand: order, navigation, lines and offsets follow the trivia', function () {
	$a = word('$a', [new Trivia(Trivia::OpenTag, "<?php\n"), new Trivia(Trivia::Whitespace, "\t")]);
	$b = word('$b', [new Trivia(Trivia::LineEnding, "\n")]);
	$eof = new Token(Token::EndOfFile, '');
	$file = new FileNode(new PlainNodeList([statement($a), statement($b)]), $eof);
	Assert::same("<?php\n\t\$a\n\n\$b\n", (string) $file);

	Assert::same([$a, $b, $eof], $file->getIndex()->getTokens());
	Assert::same($b, $a->getNext());
	Assert::same($a, $b->getPrevious());
	Assert::null($a->getPrevious());
	Assert::null($eof->getNext());

	Assert::same([2, 2, 7], [$a->currentLine, $a->currentColumn, $a->currentOffset]);
	Assert::same([4, 1, 11], [$b->currentLine, $b->currentColumn, $b->currentOffset]);
	Assert::same(5, $eof->currentLine);
	Assert::true($b->startsLine());
});


test('a change of text or trivia moves what follows, a structural change also the order', function () {
	$a = word('$a', [new Trivia(Trivia::OpenTag, "<?php\n")]);
	$b = word('$b');
	$eof = new Token(Token::EndOfFile, '');
	$first = statement($a);
	$file = new FileNode(new PlainNodeList([$first, statement($b)]), $eof);
	Assert::same(3, $b->currentLine);
	Assert::same(0, $file->revision);

	$a->setText("\$aa\n");
	Assert::same(4, $b->currentLine);
	Assert::same(11, $b->currentOffset);
	$b->setLeadingTrivia([new Trivia(Trivia::LineEnding, "\n")]);
	Assert::same(5, $b->currentLine);
	Assert::same(6, $eof->currentLine);
	Assert::same(2, $file->revision);

	$file->statements->removeItem($first);
	Assert::same(3, $file->revision);
	Assert::same([$b, $eof], $file->getIndex()->getTokens());
	Assert::same(2, $b->currentLine);
	Assert::null($b->getPrevious());
	Assert::null($a->currentLine);
	Assert::null($a->getNext());
	Assert::exception(fn() => $file->getIndex()->getOrdinal($a), InvalidArgumentException::class, 'The token does not belong to the indexed tree.');

	$file->statements->insert(0, $first);
	Assert::same([$a, $b, $eof], $file->getIndex()->getTokens());
	Assert::same(2, $a->currentLine);
	Assert::same(5, $b->currentLine);
});
