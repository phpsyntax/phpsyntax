<?php declare(strict_types=1);

use PhpSyntax\Nodes\ArgumentNode;
use PhpSyntax\Nodes\Expression\ArrayNode;
use PhpSyntax\Nodes\Expression\BinaryOpNode;
use PhpSyntax\Nodes\Statement\BlockNode;
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use PhpSyntax\Nodes\Statement\IfNode;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Parser;
use PhpSyntax\Printer;
use PhpSyntax\Style;
use PhpSyntax\Token;
use PhpSyntax\Trivia;
use PhpSyntax\TriviaKind;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


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
		'The node was taken out of the subtree, clone it first.',
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
