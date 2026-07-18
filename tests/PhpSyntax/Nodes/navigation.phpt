<?php declare(strict_types=1);

use PhpSyntax\Node;
use PhpSyntax\Nodes\ClassLikeNode;
use PhpSyntax\Nodes\Expression\BinaryOpNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Member\PropertyNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use PhpSyntax\Nodes\Statement\ReturnNode;
use PhpSyntax\Parser;
use PhpSyntax\TriviaKind;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


test('first and last tokens, lines', function () {
	$file = (new Parser)->parse("<?php\n\$a = f(\n\t1,\n\t2,\n);\n");
	$stmt = $file->statements->getItems()[0];
	Assert::same('$a', $stmt->getFirstToken()?->text);
	Assert::same(';', $stmt->getLastToken()?->text);
	Assert::same([2, 5], [$stmt->getStartLine(), $stmt->getEndLine()]);
	Assert::null($file->statements->getItems()[0]->parent?->parent?->parent);

	$heredoc = (new Parser)->parse("<?php\n\$a = <<<EOT\nx\nEOT;\n")->statements->getItems()[0];
	Assert::same([2, 4], [$heredoc->getStartLine(), $heredoc->getEndLine()]);
	Assert::null((new PhpSyntax\Nodes\NodeList)->getFirstToken());
});


test('ancestors and descendants', function () {
	$file = (new Parser)->parse('<?php class A { function m() { return $x + 1; } }');
	$class = $file->statements->getItems()[0];
	Assert::type(ClassNode::class, $class);
	$method = $class->members->getItems()[0];
	Assert::type(MethodNode::class, $method);
	$return = $method->body?->statements->getItems()[0];
	Assert::type(ReturnNode::class, $return);
	$binary = $return->expression;
	Assert::type(BinaryOpNode::class, $binary);

	Assert::same($method, $binary->findAncestor(MethodNode::class));
	Assert::same($class, $binary->findAncestor(ClassLikeNode::class));
	Assert::null($binary->findAncestor(PropertyNode::class));
	Assert::same($file, $binary->getFile());

	$descendants = $file->find(Node::class);
	Assert::same($file->statements, $descendants[0]);
	Assert::same($class, $descendants[1]);
	Assert::same([$binary, $binary->left, $binary->right], $return->find(ExpressionNode::class));
	Assert::same([$binary->left], $return->find(VariableNode::class));
	Assert::same([], $binary->left->find(Node::class));
});


test('doc comment in leading trivia or in the trailing trivia of the previous token', function () {
	$file = (new Parser)->parse("<?php\n/** a */\n// x\n\$a; /** b */ \$b; \$c;");
	[$a, $b, $c] = $file->statements->getItems();
	$doc = $a->getDocComment();
	Assert::type(PhpSyntax\Trivia::class, $doc);
	Assert::same('/** a */', $doc->text);
	Assert::same(TriviaKind::DocComment, $doc->kind);
	Assert::same('/** b */', $b->getDocComment()?->text);
	Assert::null($c->getDocComment());
	Assert::null((new PhpSyntax\Nodes\NodeList)->getDocComment());
});


test('deep clone has no parent and fresh children', function () {
	$file = (new Parser)->parse('<?php f($a, $b);');
	$stmt = $file->statements->getItems()[0];
	Assert::type(ExpressionStatementNode::class, $stmt);
	$copy = clone $stmt;
	Assert::null($copy->parent);
	Assert::same($copy, $copy->expression->parent);
	Assert::same($copy, $copy->semicolon->parent);
	Assert::notSame($stmt->expression, $copy->expression);
	Assert::same((string) $stmt, (string) $copy);
	Assert::same($stmt, $stmt->expression->parent);

	$fileCopy = clone $file;
	Assert::same((string) $file, (string) $fileCopy);
	Assert::same($fileCopy, $fileCopy->statements->getItems()[0]->parent?->parent);
	Assert::same(1, $fileCopy->getIndex()->getTokens()[0]->getLine());
});


test('a clone copies the children, never a property computed from them', function () {
	// the value of a heredoc that interpolates is an error to read, and a copy must not read it
	$file = (new Parser)->parse("<?php\n\$a = <<<EOT\n\tx {\$b} y\n\tEOT;\n");
	$copy = clone $file;
	Assert::same((string) $file, (string) $copy);

	$file = (new Parser)->parse('<?php final class A { public readonly int $x; }');
	$class = $file->statements->getItems()[0];
	Assert::type(ClassNode::class, $class);
	$copy = clone $class;
	Assert::same((string) $class, (string) $copy);
	Assert::notSame($class->modifiers->getTokens()[0], $copy->modifiers->getTokens()[0]);
	Assert::same($copy->modifiers, $copy->modifiers->getTokens()[0]->parent);

	$params = $file->find(PropertyNode::class)[0]->items;
	$copy = clone $params;
	Assert::same($copy, $copy->getItems()[0]->parent);
	Assert::notSame($params->getItems()[0], $copy->getItems()[0]);
});
