<?php declare(strict_types=1);

/**
 * Behavior shared by all generated node classes, on a few representatives.
 */

use PhpSyntax\Nodes\EmptyArrayItemNode;
use PhpSyntax\Nodes\Expression\TernaryNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\Scalar\IntegerNode;
use PhpSyntax\Nodes\Statement\BlockNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


function token(string $text, int $kind = TokenKind::Identifier): Token
{
	return new Token($kind, $text);
}


function variable(string $name): VariableNode
{
	return new VariableNode(null, null, token($name, TokenKind::Variable), null);
}


test('constructor, attach, iteration and printing', function () {
	Assert::null(variable('$a')->parent);
	$ternary = new TernaryNode(
		$cond = variable('$a'),
		$question = token('?'),
		null,
		$colon = token(':'),
		$else = variable('$b'),
	);
	Assert::same($ternary, $cond->parent);
	Assert::same($ternary, $question->parent);
	Assert::same([$cond, $question, $colon, $else], $ternary->getChildren());
	Assert::same('$a?:$b', (string) $ternary);
});


test('setters keep parents and count mutations through the file', function () {
	$ternary = new TernaryNode(variable('$a'), token('?'), null, token(':'), $else = variable('$b'));
	$ternary->then = $if = new IntegerNode(token('1', TokenKind::Integer));
	Assert::same($ternary, $if->parent);
	Assert::same('$a?1:$b', (string) $ternary);

	$ternary->else = $other = variable('$c');
	Assert::null($else->parent);
	Assert::same($ternary, $other->parent);
	Assert::exception(fn() => $ternary->condition = $other, LogicException::class, 'The node already belongs to a tree, clone it first.');

	$ternary->then = null;
	Assert::null($if->parent);
	Assert::same('$a?:$c', (string) $ternary);
});


test('replaceChild checks the slot type', function () {
	$ternary = new TernaryNode($cond = variable('$a'), $question = token('?'), null, token(':'), variable('$b'));
	$ternary->replaceChild($cond, $new = variable('$x'));
	Assert::same($ternary, $new->parent);
	Assert::null($cond->parent);
	Assert::same($new, $ternary->condition);

	Assert::exception(
		fn() => $ternary->replaceChild($question, variable('$y')),
		InvalidArgumentException::class,
		"PhpSyntax\\Nodes\\Expression\\VariableNode cannot be placed in the slot 'question' of PhpSyntax\\Nodes\\Expression\\TernaryNode.",
	);
	Assert::exception(
		fn() => $ternary->replaceChild($cond, variable('$z')),
		InvalidArgumentException::class,
		'PhpSyntax\Nodes\Expression\VariableNode is not a child of PhpSyntax\Nodes\Expression\TernaryNode.',
	);
});


test('list slots are replaced by lists only', function () {
	$block = new BlockNode(token('{'), $stmts = new NodeList, token('}'));
	Assert::same($block, $stmts->parent);
	$block->replaceChild($stmts, $other = new NodeList);
	Assert::same($other, $block->statements);
	Assert::exception(fn() => $block->replaceChild($other, token('x')), InvalidArgumentException::class, "%a% cannot be placed in the slot 'statements' %a%");
});


test('node without slots', function () {
	$item = new EmptyArrayItemNode;
	Assert::same([], $item->getChildren());
	Assert::same('', (string) $item);
	Assert::exception(fn() => $item->replaceChild(token('x'), token('y')), InvalidArgumentException::class);
});


test('union slots accept every listed type', function () {
	$statement = new ExpressionStatementNode(variable('$a'), token(';'));
	Assert::same('$a;', (string) $statement);
	$class = new ClassNode(new NodeList, new PhpSyntax\Nodes\ModifiersNode, token('class'), new PhpSyntax\Nodes\IdentifierNode(token('A')), null, null, null, null, token('{'), new NodeList, token('}'));
	Assert::same('classA{}', (string) $class);
});
