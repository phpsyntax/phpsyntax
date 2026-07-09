<?php declare(strict_types=1);

use PhpSyntax\{Node, Parser, Printer, Style, Token, TokenKind, Trivia, TriviaKind};
use PhpSyntax\Nodes\{ArgumentNode, ArrayItemNode, ExpressionNode, FileNode, NodeList, SeparatedNodeList, StatementNode};
use PhpSyntax\Nodes\Expression\{ArrayNode, BinaryOpNode};
use PhpSyntax\Nodes\Statement\{BlockNode, ExpressionStatementNode, IfNode};
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


test('visual column expands tabs', function () {
	$tokens = tokensOf("<?php\n\t\$a;\n \t\$b;\n\$x=\t\$c;");
	$style = new Style(tabWidth: 4);
	Assert::same(5, $tokens[0]->getVisualColumn($style));
	Assert::same(5, $tokens[2]->getVisualColumn($style));
	Assert::same('$c', $tokens[6]->text);
	Assert::same(5, $tokens[6]->getVisualColumn($style));
	Assert::same(9, $tokens[6]->getVisualColumn(new Style(tabWidth: 8)));
	Assert::same(2, $tokens[0]->getColumn());
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


test('the order and the lines follow mutations of every kind', function () {
	$file = (new Parser)->parse("<?php\nfunction f(\$a) {\n\treturn [\n\t\t1,\n\t\t2\n\t];\n}\nfoo(1, 2);\n\$x = 'a' . 'b';\n");
	$verify = function () use ($file): void {
		$describe = fn(Token $token) => [$token->text, $token->getLine()];
		$fresh = (new Parser)->parse(Printer::print($file));
		$index = $file->getIndex();
		Assert::same(array_map($describe, $fresh->getIndex()->getTokens()), array_map($describe, $index->getTokens()));
		foreach ($index->getTokens() as $i => $token) {
			Assert::same($i, $index->getIndex($token));
			Assert::same($index->getTokens()[$i - 1] ?? null, $token->getPrevious());
		}
	};
	$file->getLastToken()?->getLine(); // builds the index before the mutations
	$file->statements->getItems()[1]->remove(); // foo(1, 2);
	$verify();
	$file->statements->insert(1, (new Parser)->parseStatement("\$y = 1;\n")); // tokens numbered by another tree
	$verify();
	$concat = $file->find(BinaryOpNode::class)[0];
	$concat->replaceWith((new Parser)->parseExpression("'ab'"));
	$verify();
	$array = $file->find(ArrayNode::class)[0];
	$array->items->setTrailingSeparator(new Token(ord(','), ','));
	$verify();
	$semicolon = $file->getLastToken()?->getPrevious();
	Assert::type(Token::class, $semicolon);
	$semicolon->setTrailingTrivia([new Trivia(TriviaKind::EndOfLine, "\n"), new Trivia(TriviaKind::EndOfLine, "\n")]);
	$verify();
	$semicolon->setLeadingTrivia([new Trivia(TriviaKind::Whitespace, '  ')]);
	$verify();

	$other = (new Parser)->parse("<?php\n\$z;\n");
	$moved = $other->statements->getItems()[0];
	Assert::same(2, $moved->getStartLine()); // numbered by the index of the other file
	$other->statements->removeItem($moved);
	Assert::null($moved->getStartLine());
	$moved->setEdgeTrivia(leading: []); // the open tag of the other file
	$file->statements->append($moved);
	$verify();
	Assert::same(11, $moved->getStartLine()); // the statement inserted above ends its line, as its neighbor does
	Assert::same(1, $other->getLastToken()?->getLine());
});


test('a trailing separator taken away before the index caught up with its insertion', function () {
	$file = (new Parser)->parse("<?php\nf(\$a);\n");
	$list = $file->find(ArgumentNode::class)[0]->parent;
	assert($list instanceof SeparatedNodeList);
	$list->append((new Parser)->parseFragment(ArgumentNode::class, '$b'));
	$list->setTrailingSeparator(new Token(ord(','), ','));
	$list->setTrailingSeparator(null);
	Assert::same("<?php\nf(\$a, \$b);\n", Printer::print($file));
	Assert::same(2, $file->getLastToken()?->getPrevious()?->getLine());
});


test('a node moved into the subtree that replaced it stands in the order once', function () {
	// the way a body without braces is enclosed in them: the block takes the place of the statement,
	// the statement then moves into the block, and both are adopted children of the same change
	$parser = new Parser;
	$file = $parser->parse("<?php\nif (\$a)\n\t\$b = 1;\n");
	$file->getIndex()->getTokens();

	$if = $file->statements->getItems()[0];
	Assert::type(IfNode::class, $if);
	$body = $if->body;
	$block = $parser->parseStatement('{}');
	Assert::type(BlockNode::class, $block);
	Assert::type(StatementNode::class, $body);
	$body->replaceWith($block);
	$block->statements->append($body);

	$fresh = (new Parser)->parse(Printer::print($file));
	Assert::same(
		array_map(fn(Token $token) => $token->text, $fresh->getIndex()->getTokens()),
		array_map(fn(Token $token) => $token->text, $file->getIndex()->getTokens()),
	);
	$index = $file->getIndex();
	foreach ($index->getTokens() as $i => $token) {
		Assert::same($i, $index->getIndex($token));
		Assert::same($index->getTokens()[$i - 1] ?? null, $token->getPrevious());
	}
});


test('line width counts the indentation visually and drops trailing whitespace', function () {
	$file = (new Parser)->parse("<?php\n\tif (\$a) { // c   \n\t\t\$bb = 'ěšč';\t\n\t}\n");
	$style = new Style(tabWidth: 4);
	$if = $file->statements->getItems()[0];
	Assert::same(strlen('    if ($a) { // c'), $if->getFirstToken()?->getLineWidth($style));
	$assign = $file->find(ExpressionStatementNode::class)[0];
	Assert::same(strlen('        $bb = ') + 5 + 1, $assign->semicolon->getLineWidth($style));
	Assert::same(5, $file->getLastToken()?->getPrevious()?->getLineWidth($style));

	// a tab inside the line moves to the next stop as well, which is what the editor shows
	$file = (new Parser)->parse("<?php\n\$a = [\n\t'xy'\t=> 1,\n];\n");
	$item = $file->find(ArrayItemNode::class)[0];
	Assert::same(strlen("    'xy'    => 1,"), $item->getFirstToken()?->getLineWidth($style));
	Assert::same(strlen("    'xy'    ") + 1, $item->doubleArrow?->getVisualColumn($style));
});


test('offset range of a node and a token, in the current text', function () {
	$code = "<?php\n\$a = (1 + \$b);\n";
	$file = (new Parser)->parse($code);
	$index = $file->getIndex();
	$sum = $file->find(BinaryOpNode::class)[0];
	Assert::same([12, 18], $index->getOffsetRange($sum)); // '1 + $b'
	Assert::same([16, 18], $index->getOffsetRange($sum->right));
	Assert::same([14, 15], $index->getOffsetRange($sum->operator));
	Assert::null($index->getOffsetRange(new NodeList([])));

	$sum->left->getFirstToken()?->setText('100');
	Assert::same([12, 20], $index->getOffsetRange($sum));
	Assert::same([18, 20], $index->getOffsetRange($sum->right));
});


test('a node is found by its offsets: the outermost of the class, none without the exact text', function () {
	$code = "<?php\n\$a = (1 + \$b);\n";
	$file = (new Parser)->parse($code);
	$index = $file->getIndex();
	$sum = $file->find(BinaryOpNode::class)[0];
	Assert::same($sum, $index->findNode(12, 18));
	Assert::same($sum, $index->findNode(12, 18, ExpressionNode::class));
	Assert::null($index->findNode(12, 18, StatementNode::class));
	Assert::null($index->findNode(12, 17));
	Assert::same($sum->parent, $index->findNode(11, 19)); // the parentheses
	Assert::same($sum->right, $index->findNode(16, 18, ExpressionNode::class));

	// the statement and its assignment share nothing, the statement ends with the semicolon; the list of the
	// one statement stands at the same offsets and is the outermost
	$statement = $file->statements->items[0];
	Assert::same($file->statements, $index->findNode(6, 20));
	Assert::same($statement, $index->findNode(6, 20, StatementNode::class));
	Assert::null($index->findNode(6, 20, ExpressionNode::class));
	Assert::same($statement->getChildren()[0], $index->findNode(6, 19, ExpressionNode::class));

	// the map follows a mutation
	$sum->left->getFirstToken()?->setText('100');
	Assert::null($index->findNode(12, 18));
	Assert::same($sum, $index->findNode(12, 20));
	$file->statements->removeItem($statement);
	Assert::null($index->findNode(12, 20));
});
