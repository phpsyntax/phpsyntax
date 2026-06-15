<?php declare(strict_types=1);

use PhpSyntax\{Builder, Parser, Printer, Token, Trivia};
use PhpSyntax\Nodes\{ArgumentNode, FileNode, PlainNodeList, SeparatedNodeList, StatementNode};
use PhpSyntax\Nodes\Expression\{ArrayNode, BinaryOpNode};
use PhpSyntax\Nodes\Statement\{BlockNode, ExpressionStatementNode, IfNode};
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

	Assert::same([2, 2, 7], [$a->getCurrentLine(), $a->getCurrentColumn(), $a->getCurrentOffset()]);
	Assert::same([4, 1, 11], [$b->getCurrentLine(), $b->getCurrentColumn(), $b->getCurrentOffset()]);
	Assert::same(5, $eof->getCurrentLine());
	Assert::true($b->startsLine());
});


test('a change of text or trivia moves what follows, a structural change also the order', function () {
	$a = word('$a', [new Trivia(Trivia::OpenTag, "<?php\n")]);
	$b = word('$b');
	$eof = new Token(Token::EndOfFile, '');
	$first = statement($a);
	$file = new FileNode(new PlainNodeList([$first, statement($b)]), $eof);
	Assert::same(3, $b->getCurrentLine());
	Assert::same(0, $file->revision);

	$a->setText("\$aa\n");
	Assert::same(4, $b->getCurrentLine());
	Assert::same(11, $b->getCurrentOffset());
	$b->setLeadingTrivia([new Trivia(Trivia::LineEnding, "\n")]);
	Assert::same(5, $b->getCurrentLine());
	Assert::same(6, $eof->getCurrentLine());
	Assert::same(2, $file->revision);

	$file->statements->removeItem($first);
	Assert::same(3, $file->revision);
	Assert::same([$b, $eof], $file->getIndex()->getTokens());
	Assert::same(2, $b->getCurrentLine());
	Assert::null($b->getPrevious());
	Assert::null($a->getCurrentLine());
	Assert::null($a->getNext());
	Assert::exception(fn() => $file->getIndex()->getOrdinal($a), InvalidArgumentException::class, 'The token does not belong to the indexed tree.');

	$file->statements->insert(0, $first);
	Assert::same([$a, $b, $eof], $file->getIndex()->getTokens());
	Assert::same(2, $a->getCurrentLine());
	Assert::same(5, $b->getCurrentLine());
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
	Assert::same([2, 2, 8], [$call->getCurrentLine(), $call->getCurrentColumn(), $call->getCurrentOffset()]);
	Assert::same([2, 6], [$paren->getCurrentLine(), $paren->getCurrentColumn()]);
	Assert::same([2, 7], [$arg->getCurrentLine(), $arg->getCurrentColumn()]);
	$b = $tokens[5];
	Assert::same('$b', $b->text);
	Assert::same([4, 3], [$b->getCurrentLine(), $b->getCurrentColumn()]);
	Assert::same(4, $tokens[6]->getCurrentLine());
	Assert::same(2, $tokens[0]->line);
});


test('a change of trivia moves the lines, a structural change also the order', function () {
	$file = (new Parser)->parse("<?php\n\$a;\n\$b;");
	$index = $file->getIndex();
	$b = $index->getTokens()[2];
	Assert::same(3, $b->getCurrentLine());

	$a = $index->getTokens()[0];
	$a->setLeadingTrivia([new Trivia(Trivia::OpenTag, "<?php\n"), new Trivia(Trivia::LineEnding, "\n")]);
	Assert::same(4, $b->getCurrentLine());
	Assert::same(1, $file->revision);

	$stmt = $file->statements[0];
	$file->statements->removeItem($stmt);
	Assert::same(2, $file->revision);
	Assert::same($b, $index->getTokens()[0]);
	Assert::same(1, $b->getCurrentLine());
	Assert::null($b->getPrevious());
});


test('detached subtree has no positions', function () {
	$file = (new Parser)->parse('<?php $a; $b;');
	$stmt = $file->statements[0];
	Assert::type(ExpressionStatementNode::class, $stmt);
	$file->statements->removeItem($stmt);
	Assert::null($stmt->semicolon->getCurrentLine());
	Assert::null($stmt->semicolon->getNext());
	Assert::null($stmt->getFile());
	Assert::exception(fn() => $file->getIndex()->getOrdinal($stmt->semicolon), InvalidArgumentException::class, 'The token does not belong to the indexed tree.');
});


test('a subtree entering the file with a hole a write left in it is refused', function () {
	$parser = new Parser;
	$builder = new Builder;
	$file = $parser->parse("<?php\nf(1);\n");
	$file->getIndex();

	// the argument is taken out of the fragment, which is then inserted: it would stand in the order twice
	$fragment = $builder->statement('g($a);');
	$target = $file->findFirst(ArgumentNode::class);
	$source = $fragment->findFirst(ArgumentNode::class);
	Assert::type(ArgumentNode::class, $target);
	Assert::type(ArgumentNode::class, $source);
	$target->value = $source->value;
	$file->statements->append($fragment);
	Assert::exception(
		fn() => $file->endOfFile->getCurrentLine(),
		LogicException::class,
		'%a% stands in the file twice: %a%',
	);

	// the same insertion of a whole fragment is right and says so
	$file = $parser->parse("<?php\nf(1);\n");
	$file->getIndex();
	$file->statements->append($builder->statement('g($a);'));
	Assert::same("<?php\nf(1);\ng(\$a);\n", (string) $file);
	Assert::same(4, $file->endOfFile->getCurrentLine());
});


test('the order and the positions follow mutations of every kind', function () {
	$file = (new Parser)->parse("<?php\nfunction f(\$a) {\n\treturn [\n\t\t1,\n\t\t2\n\t];\n}\nfoo(1, 2);\n\$x = 'a' . 'b';\n");
	$verify = function () use ($file): void {
		$describe = fn(Token $token) => [$token->text, $token->getCurrentLine(), $token->getCurrentColumn(), $token->getCurrentOffset()];
		$fresh = (new Parser)->parse(Printer::print($file));
		$index = $file->getIndex();
		Assert::same(array_map($describe, $fresh->getIndex()->getTokens()), array_map($describe, $index->getTokens()));
		foreach ($index->getTokens() as $i => $token) {
			Assert::same($i, $index->getOrdinal($token));
			Assert::same($index->getTokens()[$i - 1] ?? null, $token->getPrevious());
		}
	};
	$file->getLastToken()->getCurrentLine(); // builds the index before the mutations
	$file->statements[1]->remove(); // foo(1, 2);
	$verify();
	$file->statements->insert(1, (new Builder)->statement("\$y = 1;\n")); // tokens numbered by another tree
	$verify();
	$concat = $file->find(BinaryOpNode::class)[0];
	$concat->replaceWith((new Builder)->expression("'ab'"));
	$verify();
	$array = $file->find(ArrayNode::class)[0];
	$array->items->setTrailingSeparator(new Token(ord(','), ','));
	$verify();
	$semicolon = $file->getLastToken()->getPrevious();
	Assert::type(Token::class, $semicolon);
	$semicolon->setTrailingTrivia([new Trivia(Trivia::LineEnding, "\n"), new Trivia(Trivia::LineEnding, "\n")]);
	$verify();
	$semicolon->setLeadingTrivia([new Trivia(Trivia::Whitespace, '  ')]);
	$verify();

	$other = (new Parser)->parse("<?php\n\$z;\n");
	$moved = $other->statements[0];
	Assert::same(2, $moved->getStartLine()); // numbered by the index of the other file
	$other->statements->removeItem($moved);
	Assert::null($moved->getStartLine());
	$moved->setEdgeTrivia(leading: []); // the open tag of the other file
	$file->statements->append($moved);
	$verify();
	Assert::same(11, $moved->getStartLine()); // the statement inserted above ends its line, as its neighbor does
	Assert::same(1, $other->getLastToken()->getCurrentLine());
});


test('offsets and columns stay right when the queries reach only part of the file', function () {
	$file = (new Parser)->parse("<?php\n\$a = f(1);\n\$b = g(2, 'é');\n\$c = h(3);\n");
	$tokens = $file->getIndex()->getTokens();
	$describe = fn(Token $token) => [$token->text, $token->getCurrentLine(), $token->getCurrentColumn(), $token->getCurrentOffset()];
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


test('writes of one batch land where the tree has them, whatever else was released', function () {
	$parser = new Parser;
	$builder = new Builder;
	$file = $parser->parse("<?php\n\$a;\nf(\$x);\nreturn;\n");
	$file->getLastToken()->getCurrentLine(); // builds the index before the writes

	// a slot replaced by longer code, then an empty slot elsewhere filled by as many tokens as were released,
	// both waiting for the next query
	$statement = $file->find(ExpressionStatementNode::class)[1];
	$statement->expression = $builder->expression('g($a, $b)');
	$return = $file->find(PhpSyntax\Nodes\Statement\ReturnNode::class)[0];
	$return->expression = $builder->expression('h($z)')->setEdgeTrivia([new Trivia(Trivia::Whitespace, ' ')]);

	$describe = fn(Token $token) => [$token->text, $token->getCurrentLine(), $token->getCurrentColumn(), $token->getCurrentOffset()];
	$fresh = $parser->parse(Printer::print($file));
	Assert::same(array_map($describe, $fresh->getIndex()->getTokens()), array_map($describe, $file->getIndex()->getTokens()));
	Assert::same("<?php\n\$a;\ng(\$a, \$b);\nreturn h(\$z);\n", Printer::print($file));

	// an item taken out and put back at the end, followed by a separator as long as the one released with it:
	// the token before the released separator leaves as well, so the separator has no place there
	$file = $parser->parse('<?php [$t, $r];');
	$file->getLastToken()->getCurrentLine();
	$list = $file->find(ArrayNode::class)[0]->items;
	$t = $list[0];
	$list->removeItem($t);
	$list->append($t, new Token(ord(','), ','));
	$list->setTrailingSeparator(new Token(ord(','), ','));
	$fresh = $parser->parse(Printer::print($file));
	Assert::same(array_map($describe, $fresh->getIndex()->getTokens()), array_map($describe, $file->getIndex()->getTokens()));
	Assert::same('<?php [$r,$t,];', Printer::print($file));
});


test('a trailing separator taken away before the index caught up with its insertion', function () {
	$file = (new Parser)->parse("<?php\nf(\$a);\n");
	$list = $file->find(ArgumentNode::class)[0]->parent;
	assert($list instanceof SeparatedNodeList);
	$list->append((new Builder)->fragment(ArgumentNode::class, '$b'));
	$list->setTrailingSeparator(new Token(ord(','), ','));
	$list->setTrailingSeparator(null);
	Assert::same("<?php\nf(\$a, \$b);\n", Printer::print($file));
	Assert::same(2, $file->getLastToken()->getPrevious()?->getCurrentLine());
});


test('a node moved into the subtree that replaced it stands in the order once', function () {
	// the way a body without braces is enclosed in them: the block takes the place of the statement,
	// the statement then moves into the block, and both are adopted children of the same change
	$parser = new Parser;
	$builder = new Builder;
	$file = $parser->parse("<?php\nif (\$a)\n\t\$b = 1;\n");
	$file->getIndex()->getTokens();

	$if = $file->statements[0];
	Assert::type(IfNode::class, $if);
	$body = $if->body;
	$block = $builder->statement('{}');
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
		Assert::same($i, $index->getOrdinal($token));
		Assert::same($index->getTokens()[$i - 1] ?? null, $token->getPrevious());
	}
});
