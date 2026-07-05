<?php declare(strict_types=1);

use PhpSyntax\CommentPolicy;
use PhpSyntax\Node;
use PhpSyntax\Nodes\Expression\AssignmentNode;
use PhpSyntax\Nodes\Expression\BinaryOpNode;
use PhpSyntax\Nodes\Expression\ParenthesizedNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use PhpSyntax\Parser;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


function parse(string $code): FileNode
{
	return (new Parser)->parse($code);
}


/** @return list<Node> */
function stmts(FileNode $file): array
{
	return $file->statements->getItems();
}


test('replaceWith keeps the surrounding trivia and the parent invariant', function () {
	$file = parse("<?php\n\t\$a = 1; // one\n\t\$b = 2;\n");
	$replacement = clone parse('<?php $x = 3;')->statements->getItems()[0];
	$first = $replacement->getFirstToken();
	Assert::type(PhpSyntax\Token::class, $first);
	$first->setLeadingTrivia([]);
	stmts($file)[0]->replaceWith($replacement);
	Assert::same("<?php\n\t\$x = 3; // one\n\t\$b = 2;\n", (string) $file);
	Assert::same($file->statements, $replacement->parent);
	Assert::true($file->revision > 0);
	Assert::same(2, $replacement->getStartLine());
	Assert::exception(fn() => $replacement->replaceWith(stmts($file)[1]), LogicException::class, 'The node already belongs to a tree, clone it first.');
});


test('a node is lifted out of the one it replaces, and only out of that one', function () {
	$file = parse("<?php\n\$a = (\$b + 1);\n\$c = \$c + \$d;\n\nreturn \$c;\n");
	$file->getIndex(); // the index is built first, so that the lift has to keep it right

	// replaceWith(), where the parentheses go and what they held stays
	$parenthesized = $file->find(ParenthesizedNode::class)[0];
	$inner = $parenthesized->expression;
	$parenthesized->replaceWith($inner);
	Assert::same("<?php\n\$a = \$b + 1;\n\$c = \$c + \$d;\n\nreturn \$c;\n", (string) $file);
	Assert::type(AssignmentNode::class, $inner->parent);

	// the same through a slot, where the right operand takes the place of the whole expression
	$assign = $file->find(AssignmentNode::class)[1];
	$binary = $assign->expression;
	Assert::type(BinaryOpNode::class, $binary);
	$assign->expression = $binary->right;
	Assert::same("<?php\n\$a = \$b + 1;\n\$c = \$d;\n\nreturn \$c;\n", (string) $file);

	// the index followed both, so the lines and the order of the tokens are right
	Assert::same(5, stmts($file)[2]->getStartLine());
	Assert::same(
		['$a', '=', '$b', '+', '1', ';', '$c', '=', '$d', ';', 'return', '$c', ';', ''],
		array_map(fn(PhpSyntax\Token $token) => $token->text, $file->getIndex()->getTokens()),
	);

	// a node from elsewhere in the tree is still refused: nothing releases it
	Assert::exception(
		fn() => $assign->expression = $file->find(AssignmentNode::class)[0]->expression,
		LogicException::class,
		'The node already belongs to a tree, clone it first.',
	);
});


test('remove takes a whole line, keeps blank lines and the open tag', function () {
	$file = parse("<?php\n\n\$a;\n\n\t\$b;\n\$c;\n");
	stmts($file)[0]->remove();
	Assert::same("<?php\n\n\n\t\$b;\n\$c;\n", (string) $file);
	stmts($file)[0]->remove();
	Assert::same("<?php\n\n\n\$c;\n", (string) $file);
	stmts($file)[0]->remove();
	Assert::same("<?php\n\n\n", (string) $file);
	Assert::count(0, stmts($file));
	Assert::true($file->revision >= 3);
});


test('comments of a removed node follow the policy', function () {
	$code = "<?php\n\$a;\n/** doc */\n\$b; // b\n\$c;\n";
	$file = parse($code);
	stmts($file)[1]->remove();
	Assert::same("<?php\n\$a;\n/** doc */\n// b\n\$c;\n", (string) $file);
	Assert::same('/** doc */', stmts($file)[1]->getDocComment()?->text);

	$file = parse($code);
	stmts($file)[1]->remove(CommentPolicy::MoveToPreviousToken);
	Assert::same("<?php\n\$a;\n/** doc */\n// b\n\$c;\n", (string) $file);
	$last = stmts($file)[0]->getLastToken();
	Assert::type(PhpSyntax\Token::class, $last);
	Assert::same('// b', $last->trailingTrivia[count($last->trailingTrivia) - 2]->text);

	$file = parse($code);
	stmts($file)[1]->remove(CommentPolicy::Drop);
	Assert::same("<?php\n\$a;\n\$c;\n", (string) $file);

	$file = parse("<?php\n\$a; /* x */ \$b; \$c;");
	stmts($file)[1]->remove(CommentPolicy::MoveToPreviousToken);
	Assert::same("<?php\n\$a; /* x */  \$c;", (string) $file);
});


test('remove is only for list items', function () {
	$stmt = stmts(parse('<?php $a;'))[0];
	Assert::type(ExpressionStatementNode::class, $stmt);
	Assert::exception(fn() => $stmt->expression->remove(), LogicException::class, 'Only an item of a list can be removed; use the setter of the slot instead.');
	Assert::exception(fn() => new VariableNode(null, null, new PhpSyntax\Token(1, 'x'), null)->replaceWith($stmt->expression), LogicException::class, 'Cannot replace a node without a parent.');
});
