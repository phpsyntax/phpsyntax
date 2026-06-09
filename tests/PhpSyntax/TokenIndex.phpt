<?php declare(strict_types=1);

use PhpSyntax\Nodes\{FileNode, PlainNodeList, StatementNode};
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use PhpSyntax\{Parser, Printer, Token, Trivia};
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


/** @return list<Token> */
function tokensOf(string $code): array
{
	return (new Parser)->parse($code)->getIndex()->getTokens();
}


test('order and navigation', function () {
	$tokens = tokensOf("<?php\n\$a = 1;\n");
	Assert::same(['$a', '=', '1', ';', ''], array_map(fn(Token $t) => $t->text, $tokens));
	Assert::same($tokens[1], $tokens[0]->getNext());
	Assert::same($tokens[0], $tokens[1]->getPrevious());
	Assert::null($tokens[0]->getPrevious());
	Assert::null($tokens[4]->getNext());
});


test('lines, columns and offsets follow trivia, CRLF and UTF-8', function () {
	$tokens = tokensOf("<?php\r\n\tžluť('a');\r\n\r\n  \$b;");
	[$call, $paren, $arg] = $tokens;
	Assert::same([2, 2, 8], [$call->currentLine, $call->currentColumn, $call->currentOffset]);
	Assert::same([2, 6], [$paren->currentLine, $paren->currentColumn]);
	Assert::same([2, 7], [$arg->currentLine, $arg->currentColumn]);
	$b = $tokens[5];
	Assert::same('$b', $b->text);
	Assert::same([4, 3], [$b->currentLine, $b->currentColumn]);
	Assert::same(4, $tokens[6]->currentLine);
	Assert::same(2, $tokens[0]->line);
});


test('a change of trivia moves the lines, a structural change also the order', function () {
	$file = (new Parser)->parse("<?php\n\$a;\n\$b;");
	$index = $file->getIndex();
	$b = $index->getTokens()[2];
	Assert::same(3, $b->currentLine);

	$a = $index->getTokens()[0];
	$a->setLeadingTrivia([new Trivia(Trivia::OpenTag, "<?php\n"), new Trivia(Trivia::LineEnding, "\n")]);
	Assert::same(4, $b->currentLine);
	Assert::same(1, $file->revision);

	$stmt = $file->statements[0];
	$file->statements->removeItem($stmt);
	Assert::same(2, $file->revision);
	Assert::same($b, $index->getTokens()[0]);
	Assert::same(1, $b->currentLine);
	Assert::null($b->getPrevious());
});


test('detached subtree has no positions', function () {
	$file = (new Parser)->parse('<?php $a; $b;');
	$stmt = $file->statements[0];
	Assert::type(ExpressionStatementNode::class, $stmt);
	$file->statements->removeItem($stmt);
	Assert::null($stmt->semicolon->currentLine);
	Assert::null($stmt->semicolon->getNext());
	Assert::null($stmt->getFile());
	Assert::exception(fn() => $file->getIndex()->getOrdinal($stmt->semicolon), InvalidArgumentException::class, 'The token does not belong to the indexed tree.');
});


test('offsets and columns stay right when the queries reach only part of the file', function () {
	$file = (new Parser)->parse("<?php\n\$a = f(1);\n\$b = g(2, 'é');\n\$c = h(3);\n");
	$tokens = $file->getIndex()->getTokens();
	$describe = fn(Token $token) => [$token->text, $token->currentLine, $token->currentColumn, $token->currentOffset];
	$verify = function () use ($file, $describe): void {
		$fresh = (new Parser)->parse(Printer::print($file));
		Assert::same(array_map($describe, $fresh->getIndex()->getTokens()), array_map($describe, $file->getIndex()->getTokens()));
	};

	Assert::same(['$b', 3, 1, 17], $describe($tokens[7])); // the queries reach no further
	$tokens[2]->setText('ff'); // before the tokens queried
	Assert::same(['$b', 3, 1, 18], $describe($tokens[7]));
	$tokens[13]->setText("'éé'"); // after them
	Assert::same([';', 3, 16, 35], $describe($tokens[15]));
	$tokens[7]->setLeadingTrivia([new Trivia(Trivia::Whitespace, "\t")]);
	Assert::same(['$b', 3, 2, 19], $describe($tokens[7]));
	$verify();
	$tokens[1]->setTrailingTrivia([new Trivia(Trivia::LineEnding, "\n")]); // `=` now ends a line
	$verify();
});
