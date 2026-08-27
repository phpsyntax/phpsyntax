<?php declare(strict_types=1);

/**
 * replaceOperator() puts an operator of another kind in place and the parentheses the new precedence asks for.
 */

use PhpSyntax\Nodes\Expression\{BinaryOpNode, CastNode, CombinedAssignmentNode, UnaryOpNode};
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\{Parser, Token};
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


function parseFile(string $code): FileNode
{
	return (new Parser)->parse($code);
}


/** The tokens of the file as the parser reads its text: the kinds agree with what the tree holds. */
function assertReadsBack(FileNode $file): void
{
	$parsed = parseFile((string) $file);
	Assert::same(
		array_map(fn(Token $token) => [$token->id, $token->text], $parsed->getTokens()),
		array_map(fn(Token $token) => [$token->id, $token->text], $file->getTokens()),
	);
}


test('a binary operator of the same precedence changes the token alone, its trivia staying', function () {
	$file = parseFile('<?php $x = $a /* c */ + $b;');
	$binary = $file->findFirst(BinaryOpNode::class) ?? throw new LogicException;
	$binary->replaceOperator('-');
	Assert::same('<?php $x = $a /* c */ - $b;', (string) $file);
	Assert::true($binary->operator->is(ord('-')));
	assertReadsBack($file);
});


test('a binary operator binding looser puts the operation in parentheses, one binding tighter takes them away', function () {
	$file = parseFile('<?php $x = $a * $b + $c;');
	$product = $file->findFirst(BinaryOpNode::class, fn(BinaryOpNode $node) => $node->operator->is('*')) ?? throw new LogicException;
	$product->replaceOperator('??');
	Assert::same('<?php $x = ($a ?? $b) + $c;', (string) $file);
	assertReadsBack($file);

	$product->replaceOperator('*');
	Assert::same('<?php $x = $a * $b + $c;', (string) $file);
	assertReadsBack($file);

	// the operands follow the new precedence too
	$file = parseFile('<?php $x = $a + $b . $c;');
	$concat = $file->findFirst(BinaryOpNode::class, fn(BinaryOpNode $node) => $node->operator->is('.')) ?? throw new LogicException;
	$concat->replaceOperator('*');
	Assert::same('<?php $x = ($a + $b) * $c;', (string) $file);
	assertReadsBack($file);

	$file = parseFile('<?php $ok = $a && $b;');
	$and = $file->findFirst(BinaryOpNode::class) ?? throw new LogicException;
	$and->replaceOperator('and');
	Assert::same('<?php $ok = ($a and $b);', (string) $file);
	Assert::true($and->operator->is(Token::LogicalAnd));
	assertReadsBack($file);
});


test('a unary operator, a combined assignment and a cast', function () {
	$file = parseFile('<?php $x = -$a ** 2;');
	$minus = $file->findFirst(UnaryOpNode::class) ?? throw new LogicException;
	$minus->replaceOperator('!');
	Assert::same('<?php $x = !$a ** 2;', (string) $file);
	assertReadsBack($file);

	$file = parseFile('<?php $a += $b;');
	$assignment = $file->findFirst(CombinedAssignmentNode::class) ?? throw new LogicException;
	$assignment->replaceOperator('.=');
	Assert::same('<?php $a .= $b;', (string) $file);
	assertReadsBack($file);

	$file = parseFile('<?php $a = (int) $b;');
	$cast = $file->findFirst(CastNode::class) ?? throw new LogicException;
	$cast->replaceOperator('(string)');
	Assert::same('<?php $a = (string) $b;', (string) $file);
	Assert::same('string', $cast->typeName);
	assertReadsBack($file);
});


test('what is no operator of the node is refused, the tree staying as it was', function () {
	$file = parseFile('<?php $a + $b; $c += 1; -$d; (int) $e;');
	$binary = $file->findFirst(BinaryOpNode::class) ?? throw new LogicException;
	$assignment = $file->findFirst(CombinedAssignmentNode::class) ?? throw new LogicException;
	$unary = $file->findFirst(UnaryOpNode::class) ?? throw new LogicException;
	$cast = $file->findFirst(CastNode::class) ?? throw new LogicException;
	Assert::exception(fn() => $binary->replaceOperator('+='), InvalidArgumentException::class, '`+=` is not a binary operator.');
	Assert::exception(fn() => $assignment->replaceOperator('='), InvalidArgumentException::class, '`=` is not a combined assignment operator.');
	Assert::exception(fn() => $unary->replaceOperator('++'), InvalidArgumentException::class, '`++` is not a unary operator.');
	Assert::exception(fn() => $cast->replaceOperator('(foo)'), InvalidArgumentException::class, '`(foo)` is not a cast.');
	Assert::same('<?php $a + $b; $c += 1; -$d; (int) $e;', (string) $file);
});
