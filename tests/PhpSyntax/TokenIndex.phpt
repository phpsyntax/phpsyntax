<?php declare(strict_types=1);

use PhpSyntax\{Node, Parser, Token, TokenKind, Trivia, TriviaKind};
use PhpSyntax\Nodes\{ArgumentNode, FileNode, NodeList, StatementNode};
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
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
	Assert::same([2, 2, 8], [$call->getLine(), $call->getColumn(), $call->getOffset()]);
	Assert::same([2, 6], [$paren->getLine(), $paren->getColumn()]);
	Assert::same([2, 7], [$arg->getLine(), $arg->getColumn()]);
	$b = $tokens[5];
	Assert::same('$b', $b->text);
	Assert::same([4, 3], [$b->getLine(), $b->getColumn()]);
	Assert::same(4, $tokens[6]->getLine());
	Assert::same(2, $tokens[0]->originalLine);
});


test('a change of trivia moves the lines, a structural change also the order', function () {
	$file = (new Parser)->parse("<?php\n\$a;\n\$b;");
	$index = $file->getIndex();
	$b = $index->getTokens()[2];
	Assert::same(3, $b->getLine());

	$a = $index->getTokens()[0];
	$a->setLeadingTrivia([new Trivia(TriviaKind::OpenTag, "<?php\n"), new Trivia(TriviaKind::EndOfLine, "\n")]);
	Assert::same(4, $b->getLine());
	Assert::same(1, $file->revision);

	$stmt = $file->statements->getItems()[0];
	$file->statements->removeItem($stmt);
	Assert::same(2, $file->revision);
	Assert::same($b, $index->getTokens()[0]);
	Assert::same(1, $b->getLine());
	Assert::null($b->getPrevious());
});


test('detached subtree has no positions', function () {
	$file = (new Parser)->parse('<?php $a; $b;');
	$stmt = $file->statements->getItems()[0];
	Assert::type(ExpressionStatementNode::class, $stmt);
	$file->statements->removeItem($stmt);
	Assert::null($stmt->semicolon->getLine());
	Assert::null($stmt->semicolon->getNext());
	Assert::null($stmt->getFile());
	Assert::exception(fn() => $file->getIndex()->getIndex($stmt->semicolon), InvalidArgumentException::class, 'The token does not belong to the indexed tree.');
});


test('a subtree entering the file with a hole a write left in it is refused', function () {
	$parser = new Parser;
	$file = $parser->parse("<?php\nf(1);\n");
	$file->getIndex();

	// the argument is taken out of the fragment, which is then inserted: it would stand in the order twice
	$fragment = $parser->parseStatement('g($a);');
	$target = $file->findFirst(ArgumentNode::class);
	$source = $fragment->findFirst(ArgumentNode::class);
	Assert::type(ArgumentNode::class, $target);
	Assert::type(ArgumentNode::class, $source);
	$target->value = $source->value;
	$file->statements->append($fragment);
	Assert::exception(
		fn() => $file->endOfFile->getLine(),
		LogicException::class,
		'%a% stands in the file twice: %a%',
	);

	// the same insertion of a whole fragment is right and says so
	$file = $parser->parse("<?php\nf(1);\n");
	$file->getIndex();
	$file->statements->append($parser->parseStatement('g($a);'));
	Assert::same("<?php\nf(1);\ng(\$a);\n", (string) $file);
	Assert::same(4, $file->endOfFile->getLine());
});
