<?php declare(strict_types=1);

/**
 * Behavior shared by all generated node classes, on a few representatives.
 */

use PhpSyntax\Nodes\Expression\{TernaryNode, VariableNode};
use PhpSyntax\Nodes\{PlainNodeList, SkippedArrayItemNode};
use PhpSyntax\Nodes\Scalar\IntegerNode;
use PhpSyntax\Nodes\Statement\{BlockNode, ClassNode, ExpressionStatementNode};
use PhpSyntax\Token;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


function token(string $text, int $kind = Token::Identifier): Token
{
	return new Token($kind, $text);
}


function variable(string $name): VariableNode
{
	return new VariableNode(null, null, token($name, Token::Variable), null);
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
	$ternary->then = $if = new IntegerNode(token('1', Token::Integer));
	Assert::same($ternary, $if->parent);
	Assert::same('$a?1:$b', (string) $ternary);

	$ternary->else = $other = variable('$c');
	Assert::null($else->parent);
	Assert::same($ternary, $other->parent);
	assertRefused(fn() => $ternary->condition = $other, LogicException::class, 'The node already belongs to a tree; a copy comes from `withoutEdgeTrivia()`, or from `clone` with the trivia on its edges.', $ternary);

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

	assertRefused(
		fn() => $ternary->replaceChild($question, variable('$y')),
		InvalidArgumentException::class,
		'`PhpSyntax\\Nodes\\Expression\\VariableNode` cannot be placed in the slot `question` of `PhpSyntax\\Nodes\\Expression\\TernaryNode`.',
		$ternary,
	);
	assertRefused(
		fn() => $ternary->replaceChild($cond, variable('$z')),
		InvalidArgumentException::class,
		'`PhpSyntax\Nodes\Expression\VariableNode` is not a child of `PhpSyntax\Nodes\Expression\TernaryNode`.',
		$ternary,
	);
});


test('list slots are replaced by lists only', function () {
	$block = new BlockNode(token('{'), $stmts = new PlainNodeList, token('}'));
	Assert::same($block, $stmts->parent);
	$block->replaceChild($stmts, $other = new PlainNodeList);
	Assert::same($other, $block->statements);
	assertRefused(fn() => $block->replaceChild($other, token('x')), InvalidArgumentException::class, '%a% cannot be placed in the slot `statements` %a%', $block);
});


test('node without slots', function () {
	$item = new SkippedArrayItemNode;
	Assert::same([], $item->getChildren());
	Assert::same('', (string) $item);
	assertRefused(fn() => $item->replaceChild(token('x'), token('y')), InvalidArgumentException::class, null, $item);
});


test('union slots accept every listed type', function () {
	$statement = new ExpressionStatementNode(variable('$a'), token(';'));
	Assert::same('$a;', (string) $statement);
	$class = new ClassNode(new PlainNodeList, new PhpSyntax\Nodes\ModifiersNode, token('class'), new PhpSyntax\Nodes\IdentifierNode(token('A')), null, null, null, null, token('{'), new PlainNodeList, token('}'));
	Assert::same('classA{}', (string) $class);
});


test('a node is attributed exactly when it has the slot of attributes', function () {
	$mismatched = [];
	$root = dirname(__DIR__, 3) . '/src/Nodes';
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
		$class = 'PhpSyntax\Nodes\\' . strtr(substr($file->getPathname(), strlen($root) + 1, -4), '/', '\\');
		if (!class_exists($class) || !is_subclass_of($class, PhpSyntax\Node::class)) {
			continue;
		}

		$slotted = in_array('attributes', $class::Slots ?? [], true);
		if ($slotted !== is_subclass_of($class, PhpSyntax\Nodes\AttributeAwareNode::class)) {
			$mismatched[] = $class;
		}
	}

	Assert::same([], $mismatched);
});
