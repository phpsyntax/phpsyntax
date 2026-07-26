<?php declare(strict_types=1);

use PhpSyntax\CommentPolicy;
use PhpSyntax\Node;
use PhpSyntax\Nodes\ArgumentNode;
use PhpSyntax\Nodes\Expression\AssignmentNode;
use PhpSyntax\Nodes\Expression\BinaryOpNode;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Expression\MethodCallNode;
use PhpSyntax\Nodes\Expression\ParenthesizedNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\NodeList;
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


test('a node is taken out of a subtree without a file, which nothing indexes', function () {
	$parser = new Parser;
	$file = parse("<?php\n\$rows = db_query(\$db, 'SELECT 1');\n");
	$file->getIndex();

	// the codemod written in the order it is thought: replace the call, then take what it held
	$call = $file->find(FunctionCallNode::class)[0];
	$connection = $call->arguments->findArgument('connection', 0);
	$query = $call->arguments->findArgument('query', 1);
	$replacement = $parser->parseExpression('$db->query($sql)');
	Assert::type(ArgumentNode::class, $connection);
	Assert::type(ArgumentNode::class, $query);
	Assert::type(MethodCallNode::class, $replacement);
	$call->replaceWith($replacement);
	$replacement->object = $connection->value;
	$argument = $replacement->arguments->items->getItems()[0];
	Assert::type(ArgumentNode::class, $argument);
	$argument->value = $query->value;
	Assert::same("<?php\n\$rows = \$db->query('SELECT 1');\n", (string) $file);
	Assert::same(3, $file->endOfFile->getLine());

	// a piece of a fragment goes the same way
	$fragment = $parser->parseStatement('g($a);');
	$statement = $parser->parseStatement('f(1);');
	$target = $statement->findFirst(ArgumentNode::class);
	$source = $fragment->findFirst(ArgumentNode::class);
	Assert::type(ArgumentNode::class, $target);
	Assert::type(ArgumentNode::class, $source);
	$target->value = $source->value;
	Assert::same('f($a);', (string) $statement);

	// a child of the node doing the write is not taken from anywhere: it would be listed twice
	$list = new NodeList([$first = $parser->parseStatement('f();'), $parser->parseStatement('g();')]);
	Assert::exception(
		fn() => $list->append($first),
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


test('an item of a list goes with the separator that goes with it', function () {
	$remove = function (string $code, int $index): string {
		$file = parse($code);
		$list = $file->findFirst(PhpSyntax\Nodes\SeparatedNodeList::class);
		$list?->getItems()[$index]->remove();
		return (string) $file;
	};

	// the last item of a list written on several lines takes its line, the trailing comma stays behind
	$multiline = "<?php\n\$c = [\n\t'a' => 1,\n\t'b' => 2,\n\t'c' => 3,\n];\n";
	Assert::same("<?php\n\$c = [\n\t'a' => 1,\n\t'b' => 2,\n];\n", $remove($multiline, 2));
	Assert::same("<?php\n\$c = [\n\t'b' => 2,\n\t'c' => 3,\n];\n", $remove($multiline, 0));
	Assert::same("<?php\n\$c = [\n\t'a' => 1,\n\t'b' => 2\n];\n", $remove("<?php\n\$c = [\n\t'a' => 1,\n\t'b' => 2,\n\t'c' => 3\n];\n", 2));

	// on one line the gap the separator opened goes with it
	Assert::same('<?php f(1, 2);', $remove('<?php f(1, 2, 3);', 2));
	Assert::same('<?php f(1, 3);', $remove('<?php f(1, 2, 3);', 1));
	Assert::same('<?php f( 1 , 3 );', $remove('<?php f( 1 , 2 , 3 );', 1));
	Assert::same('<?php f($a,);', $remove('<?php f($a, $c,);', 1));
	Assert::same('<?php f();', $remove('<?php f(1);', 0));

	// what ends the line stays, and nothing dangles before it
	Assert::same("<?php \$x = [1,\n\t3];", $remove("<?php \$x = [1, 2,\n\t3];", 1));
});


test('remove inside a line keeps the whitespace around', function () {
	$file = parse('<?php $a; $b; $c;');
	stmts($file)[1]->remove();
	Assert::same('<?php $a;  $c;', (string) $file);
	stmts($file)[0]->remove();
	Assert::same('<?php   $c;', (string) $file);
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

	// a comment on a line of its own keeps the indentation of that line wherever it goes
	$indented = "<?php\nfunction f()\n{\n\t// note\n\t\$b = 1;\n\n\treturn 1;\n}\n";
	$file = parse($indented);
	$file->find(ExpressionStatementNode::class)[0]->remove(CommentPolicy::Drop);
	Assert::same("<?php\nfunction f()\n{\n\n\treturn 1;\n}\n", (string) $file);

	$file = parse($indented);
	$file->find(ExpressionStatementNode::class)[0]->remove(CommentPolicy::MoveToPreviousToken);
	Assert::same("<?php\nfunction f()\n{\n\t// note\n\n\treturn 1;\n}\n", (string) $file);
});


test('a refused write leaves the tree as it stood', function () {
	// the type of the slot decides before the trivia around the node move
	$file = parse('<?php /* keep */ $a;');
	$stmt = stmts($file)[0];
	Assert::type(ExpressionStatementNode::class, $stmt);
	Assert::exception(
		fn() => $stmt->expression->replaceWith((new Parser)->parseStatement('return;')),
		InvalidArgumentException::class,
		"PhpSyntax\\Nodes\\Statement\\ReturnNode cannot be placed in the slot 'expression' of PhpSyntax\\Nodes\\Statement\\ExpressionStatementNode.",
	);
	Assert::same('<?php /* keep */ $a;', (string) $file);

	// a sibling is refused before the item it would replace is released, with the index built and without
	foreach ([true, false] as $indexed) {
		$file = parse('<?php f($a, $b);');
		if ($indexed) {
			$file->getIndex();
		}

		$call = $file->findFirst(FunctionCallNode::class);
		Assert::type(FunctionCallNode::class, $call);
		$items = $call->arguments->items;
		[$first, $second] = $items->getItems();
		Assert::exception(
			fn() => $items->replaceChild($first, $second),
			LogicException::class,
			'The node already belongs to a tree, clone it first.',
		);
		Assert::same($items, $first->parent);
		Assert::same([$first, $second], $items->getItems());
		Assert::same('<?php f($a, $b);', (string) $file);
	}

	// an item already in the list is refused before it takes the line ending of its neighbor
	$file = parse("<?php\n\$a;\n\$b;\n");
	Assert::exception(
		fn() => $file->statements->append($file->statements->getItems()[1]),
		LogicException::class,
		'The node already belongs to a tree, clone it first.',
	);
	Assert::same("<?php\n\$a;\n\$b;\n", (string) $file);
});


test('an insertion of an item and a separator checks both before either moves', function () {
	$parser = new Parser;
	$file = parse('<?php f($a, $b);');
	$call = $file->findFirst(FunctionCallNode::class);
	Assert::type(FunctionCallNode::class, $call);
	$items = $call->arguments->items;

	// the separator stands in the list already, so the item stays in the fragment it came from
	$fragment = $parser->parseExpression('g($c)');
	Assert::type(FunctionCallNode::class, $fragment);
	$item = $fragment->arguments->items->getItems()[0];
	Assert::exception(
		fn() => $items->append($item, $items->getSeparators()[0]),
		LogicException::class,
		'The node already belongs to a tree, clone it first.',
	);
	Assert::same($fragment->arguments->items, $item->parent);
	Assert::same('g($c)', (string) $fragment);

	// a separator taken out of the item itself would stand in two lists at once
	$fragment = $parser->parseExpression('g([$c, $d])');
	Assert::type(FunctionCallNode::class, $fragment);
	$item = $fragment->arguments->items->getItems()[0];
	Assert::type(ArgumentNode::class, $item);
	Assert::type(PhpSyntax\Nodes\Expression\ArrayNode::class, $item->value);
	$inner = $item->value->items;
	$separator = $inner->getSeparators()[0];
	Assert::exception(
		fn() => $items->append($item, $separator),
		LogicException::class,
		'The separator cannot be a part of the item it separates.',
	);
	Assert::same($fragment->arguments->items, $item->parent);
	Assert::same($inner, $separator->parent);
	Assert::same('g([$c, $d])', (string) $fragment);

	Assert::same('<?php f($a, $b);', (string) $file);
	Assert::same($file->getTokens(), $file->getIndex()->getTokens());
});


test('a node cannot be placed inside itself', function () {
	$node = (new Parser)->parseExpression('($a)');
	Assert::type(ParenthesizedNode::class, $node);
	Assert::exception(
		fn() => $node->expression = $node,
		LogicException::class,
		'A node cannot be placed inside itself or inside what it holds.',
	);
	Assert::same('($a)', (string) $node);
	Assert::null($node->parent);

	// nor may what holds it be written below it
	$file = parse('<?php f($a);');
	$call = $file->findFirst(FunctionCallNode::class);
	Assert::type(FunctionCallNode::class, $call);
	$argument = $call->arguments->items->getItems()[0];
	Assert::type(ArgumentNode::class, $argument);
	Assert::exception(
		fn() => $argument->value = $call,
		LogicException::class,
		'A node cannot be placed inside itself or inside what it holds.',
	);
	Assert::same('<?php f($a);', (string) $file);
});


test('a slot is written only by the name it has', function () {
	$node = (new Parser)->parseExpression('($a)');
	Assert::type(ParenthesizedNode::class, $node);
	Assert::exception(
		fn() => $node->setSlot('expresion', (new Parser)->parseExpression('$b')),
		InvalidArgumentException::class,
		"There is no slot 'expresion' in PhpSyntax\\Nodes\\Expression\\ParenthesizedNode.",
	);
	Assert::exception(
		fn() => $node->setSlot('expression', new PhpSyntax\Token(1, 'x')),
		InvalidArgumentException::class,
		"Token 'x' cannot be placed in the slot 'expression' of PhpSyntax\\Nodes\\Expression\\ParenthesizedNode.",
	);
	Assert::same('($a)', (string) $node); // a refused write leaves the node as it was
});


test('remove is only for list items', function () {
	$stmt = stmts(parse('<?php $a;'))[0];
	Assert::type(ExpressionStatementNode::class, $stmt);
	Assert::exception(fn() => $stmt->expression->remove(), LogicException::class, 'Only an item of a list can be removed; use the setter of the slot instead.');
	Assert::exception(fn() => new VariableNode(null, null, new PhpSyntax\Token(1, 'x'), null)->replaceWith($stmt->expression), LogicException::class, 'Cannot replace a node without a parent.');
});
