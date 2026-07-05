<?php declare(strict_types=1);

use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use PhpSyntax\Parser;
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


test('a change of trivia moves the lines', function () {
	$file = (new Parser)->parse("<?php\n\$a;\n\$b;");
	$index = $file->getIndex();
	$b = $index->getTokens()[2];
	Assert::same(3, $b->getLine());

	$a = $index->getTokens()[0];
	$a->setLeadingTrivia([new Trivia(TriviaKind::OpenTag, "<?php\n"), new Trivia(TriviaKind::EndOfLine, "\n")]);
	Assert::same(4, $b->getLine());
	Assert::same(1, $file->revision);
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
