<?php declare(strict_types=1);

use PhpSyntax\{Builder, CommentPolicy, Node, Parser};
use PhpSyntax\Nodes\{ArgumentNode, FileNode, PlainNodeList, SeparatedNodeList};
use PhpSyntax\Nodes\Expression\{ArrayNode, AssignmentNode, BinaryOpNode, FunctionCallNode, MethodCallNode, ParenthesizedNode, VariableNode};
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
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


/** @return SeparatedNodeList<covariant Node> */
function emptyArguments(): SeparatedNodeList
{
	$call = (new Builder)->expression('f()');
	Assert::type(FunctionCallNode::class, $call);
	return clone $call->arguments->items;
}


/** @return list<array{?int, string, string, string}>  offset, leading trivia, text and trailing trivia of each token */
function describeTokens(FileNode $file): array
{
	return array_map(fn($token) => [
		$token->currentOffset,
		implode('', array_map(fn($trivia) => $trivia->text, $token->leadingTrivia)),
		$token->text,
		implode('', array_map(fn($trivia) => $trivia->text, $token->trailingTrivia)),
	], $file->getTokens());
}


/** Asserts that every token carries the trivia the parser gives it in the printed text. */
function assertAsReparsed(FileNode $file): void
{
	Assert::same(describeTokens(parse((string) $file)), describeTokens($file), (string) $file);
}


test('writing what a place already holds changes nothing', function () {
	$code = "<?php\nf(\$a + \$b, \$c,);\n";
	$file = parse($code);
	$binary = $file->findFirst(BinaryOpNode::class) ?? throw new LogicException;
	$call = $file->findFirst(FunctionCallNode::class) ?? throw new LogicException;
	$arguments = $call->arguments->items;
	$modifiers = parse('<?php class A { public static $x; }')->findFirst(PhpSyntax\Nodes\ModifiersNode::class, fn($modifiers) => !$modifiers->isEmpty()) ?? throw new LogicException;
	$revision = $file->revision;

	$binary->left = $binary->left;
	$binary->replaceChild($binary->operator, $binary->operator);
	$binary->replaceWith($binary);
	$arguments->replaceChild($arguments[1], $arguments[1]);
	$arguments->replaceChild($arguments->getSeparators()[0], $arguments->getSeparators()[0]);
	$arguments->setTrailingSeparator($arguments->getSeparators()[1]);
	$file->statements->replaceChild($file->statements[0], $file->statements[0]);
	$file->endOfFile = $file->endOfFile;
	$modifiers->replaceChild($modifiers->getTokens()[0], $modifiers->getTokens()[0]);

	Assert::same($code, (string) $file);
	Assert::same($revision, $file->revision);
	Assert::same($binary, $binary->left->parent);

	// the text and the trivia a token has, a name and the whitespace there already
	$code = "<?php\nif (\$a) {\n\n\t\$x = -(int) \$b . foo();\n\t\$y .= 'z';\n}\n";
	$file = parse($code);
	$x = $file->findFirst(PhpSyntax\Nodes\Expression\VariableNode::class, fn($variable) => $variable->plainName === 'x')?->getFirstToken() ?? throw new LogicException;
	$x->setText($x->text)->setLeadingTrivia($x->leadingTrivia)->setTrailingTrivia($x->trailingTrivia);
	$x->setTrailingSpace(' ')->setIndentation("\t")->setBlankLinesBefore(1, "\n")->ensureStartsLine("\n");
	$name = $file->findFirst(FunctionCallNode::class)?->name;
	Assert::type(PhpSyntax\Nodes\NameNode::class, $name);
	$name->text = 'foo';
	Assert::same($code, (string) $file);
	Assert::same(0, $file->revision);

	// in a node standing nowhere as well
	$detached = (new Builder)->expression('$a + $b');
	assert($detached instanceof BinaryOpNode);
	$detached->right = $detached->right;
	Assert::same('$a + $b', (string) $detached);

	// a node not standing there is still refused
	Assert::exception(fn() => $binary->replaceChild($detached->left, $detached->left), InvalidArgumentException::class);
});


test('replaceWith keeps the surrounding trivia and the parent invariant', function () {
	$file = parse("<?php\n\t\$a = 1; // one\n\t\$b = 2;\n");
	$replacement = clone parse('<?php $x = 3;')->statements[0];
	$first = $replacement->getFirstToken();
	$first->setLeadingTrivia([]);
	stmts($file)[0]->replaceWith($replacement);
	Assert::same("<?php\n\t\$x = 3; // one\n\t\$b = 2;\n", (string) $file);
	Assert::same($file->statements, $replacement->parent);
	Assert::true($file->revision > 0);
	Assert::same(2, $replacement->getStartLine());
	Assert::exception(fn() => $replacement->replaceWith(stmts($file)[1]), LogicException::class, 'The node already belongs to a tree, `clone` it first.');
});


test('replaceWith a node without tokens gives the trivia around the old one to its neighbors', function () {
	$check = function (string $code, Closure $find, string $expected): void {
		$file = parse($code);
		$old = $find($file);
		Assert::type(Node::class, $old);
		$old->replaceWith($old instanceof SeparatedNodeList ? emptyArguments() : clone parse('')->statements);
		Assert::same($expected, (string) $file);
		// the trivia stand where the lexer puts them, and the index knows the offsets
		Assert::same(describeTokens(parse($expected)), describeTokens($file));
	};

	$statements = fn(FileNode $file) => $file->statements;
	$block = fn(FileNode $file) => $file->findFirst(PhpSyntax\Nodes\Statement\BlockNode::class)?->statements;
	$check("<?php /* keep */ foo();\n", $statements, "<?php /* keep */ \n");
	$check("<?php\r\n/** doc */\r\nfunction f() {}\r\n", $statements, "<?php\r\n/** doc */\r\n\r\n");
	$check("<?php\nif (\$a) {\n\tfoo(); // c\n}\n", $block, "<?php\nif (\$a) {\n\t // c\n}\n");
	$check("<?php { g(); /* c */ } h();\n", $block, "<?php {  /* c */ } h();\n");
	$check("<?php f( \$a, \$b );\n", fn(FileNode $file) => $file->findFirst(FunctionCallNode::class)?->arguments->items, "<?php f(  );\n");
});


test('replaceWith a node without tokens refuses where no token is left to take the trivia', function () {
	$type = (new Builder)->type('A|B');
	Assert::type(PhpSyntax\Nodes\Type\UnionTypeNode::class, $type);
	$types = $type->types;
	$types->setEdgeTrivia([new PhpSyntax\Trivia(PhpSyntax\Trivia::Comment, '/* c */')], []);
	Assert::exception(
		fn() => $types->replaceWith(emptyArguments()),
		LogicException::class,
		'The trivia around the node have no token to stay with once a node without tokens takes its place.',
	);
	Assert::same($type, $types->parent);
	Assert::same('/* c */A|B', (string) $type);
});


test('replaceWith keeps apart the tokens that would be read together', function () {
	$replace = function (string $code, string $find, string $with): string {
		$file = parse("<?php\n$code\n");
		$node = $file->findFirst(PhpSyntax\Nodes\ExpressionNode::class, fn($node) => $node->text === $find);
		Assert::type(PhpSyntax\Nodes\ExpressionNode::class, $node);
		$node->replaceWith((new Builder)->expression($with));
		Assert::same((string) $file, (string) parse((string) $file));
		return substr((string) $file, 6, -1);
	};

	Assert::same("\$x = 'a'. 119 + 1;", $replace("\$x = 'a'.f();", 'f()', '119 + 1')); // .119 is a number
	Assert::same("\$x = 4 .'s';", $replace("\$x = f().'s';", 'f()', '4')); // and so is 4.
	Assert::same('return FOO;', $replace('return(1);', '(1)', 'FOO'));
	Assert::same('$x = - -$a;', $replace('$x = -f();', 'f()', '-$a'));

	// what reads the same either way stays as it was
	Assert::same("\$x = 'a'.\$b;", $replace("\$x = 'a'.f();", 'f()', '$b'));
	Assert::same('$x = !$b;', $replace('$x = !f();', 'f()', '$b'));
	Assert::same('$x = "a{$b}c";', $replace('$x = "a{$a}c";', '$a', '$b'));
	Assert::same('$x = "a{$b[1]}";', $replace('$x = "a{$b[0]}";', '0', '1'));

	// in a tree without a file too
	$negation = (new Builder)->expression('-0');
	Assert::type(PhpSyntax\Nodes\Expression\UnaryOpNode::class, $negation);
	$negation->expression->replaceWith((new Builder)->expression('-$a'));
	Assert::same('- -$a', (string) $negation);
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
		'The node already belongs to a tree, `clone` it first.',
	);

	// and so it is by an empty slot of a node standing nowhere, which is written the way a new node is built
	$yield = (new Builder)->expression('yield');
	Assert::type(PhpSyntax\Nodes\Expression\YieldNode::class, $yield);
	Assert::exception(
		fn() => $yield->value = $assign->expression,
		LogicException::class,
		'The node already belongs to a tree, `clone` it first.',
	);
	Assert::null($yield->value);
	Assert::same($assign, $assign->expression->parent);
});


test('a node is taken out of a subtree without a file, which nothing indexes', function () {
	$builder = new Builder;
	$file = parse("<?php\n\$rows = db_query(\$db, 'SELECT 1');\n");
	$file->getIndex();

	// the codemod written in the order it is thought: replace the call, then take what it held
	$call = $file->find(FunctionCallNode::class)[0];
	$connection = $call->arguments->findArgument('connection', 0);
	$query = $call->arguments->findArgument('query', 1);
	$replacement = $builder->expression('$db->query($sql)');
	Assert::type(ArgumentNode::class, $connection);
	Assert::type(ArgumentNode::class, $query);
	Assert::type(MethodCallNode::class, $replacement);
	$call->replaceWith($replacement);
	$replacement->object = $connection->value;
	$argument = $replacement->arguments->items[0];
	Assert::type(ArgumentNode::class, $argument);
	$argument->value = $query->value;
	Assert::same("<?php\n\$rows = \$db->query('SELECT 1');\n", (string) $file);
	Assert::same(3, $file->endOfFile->currentLine);

	// a piece of a fragment goes the same way
	$fragment = $builder->statement('g($a);');
	$statement = $builder->statement('f(1);');
	$target = $statement->findFirst(ArgumentNode::class);
	$source = $fragment->findFirst(ArgumentNode::class);
	Assert::type(ArgumentNode::class, $target);
	Assert::type(ArgumentNode::class, $source);
	$target->value = $source->value;
	Assert::same('f($a);', (string) $statement);

	// a child of the node doing the write is not taken from anywhere: it would be listed twice
	$list = new PlainNodeList([$first = $builder->statement('f();'), $builder->statement('g();')]);
	Assert::exception(
		fn() => $list->append($first),
		LogicException::class,
		'The node already belongs to a tree, `clone` it first.',
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


test('a statement ended by a close tag leaves the tag, so the text after it stays text', function () {
	$remove = function (string $code, int $index): string {
		$file = parse($code);
		stmts($file)[$index]->remove();
		$output = (string) $file;
		parse($output); // still code where it was code and text where it was text
		return $output;
	};

	Assert::same("<?php ?>\n<p>text</p>\n", $remove("<?php \$form->render() ?>\n<p>text</p>\n", 0));
	Assert::same("<?php\nfoo();\n?>\n<b>x</b>\n", $remove("<?php\nfoo();\nbar() ?>\n<b>x</b>\n", 1));
	Assert::same('<?php foo(); /* c */ ?>tail', $remove('<?php foo(); /* c */ bar() ?>tail', 1));
	Assert::same("<?php\n\tfoo();\n\t?>\nhtml", $remove("<?php\n\tfoo();\n\tbar()?>\nhtml", 1));
	Assert::same("<?php\n\tfoo();\n\t?>html", $remove("<?php\n\tfoo();\n\tbar() ?>html", 1));
	Assert::same('x<?php if ($a): ?>y<?php ?>z<?php endif ?>', (string) (function () {
		$file = parse('x<?php if ($a): ?>y<?php echo 1 ?>z<?php endif ?>');
		$file->findFirst(PhpSyntax\Nodes\Statement\EchoNode::class)?->remove();
		return $file;
	})());

	// a statement that opens its code too goes whole, and so does the empty statement of a close tag
	Assert::same('xy', $remove('x<?= $a ?>y', 1));
	Assert::same('<?php foo(); ', $remove("<?php foo(); ?>\n", 1));
});


test('a statement next to inline HTML is removed with the text outside the code as it was', function () {
	$html = fn(string $code): string => (string) preg_replace('~<\?php\s.*?(?:\?>\n?|$)~s', '', $code);
	$cases = [
		["<?php 1; ?>\nhtml\n<?php 2; ?>\nmore", 3, CommentPolicy::MoveToNextToken],
		["<?php 1; ?>\nhtml\n<?php /* c */ 2; ?>\nmore", 3, CommentPolicy::MoveToNextToken],
		["<?php 1; ?>\nhtml\n<?php\n\t// c\n\t2; ?>\nmore", 3, CommentPolicy::MoveToNextToken],
		["<?php 1; ?>\nhtml\n<?php\n\t// c\n\t2; ?>\nmore", 3, CommentPolicy::MoveToPreviousToken],
		["x\n<?php // c\n1;\n?>\nhtml", 1, CommentPolicy::MoveToPreviousToken],
	];
	foreach ($cases as [$code, $index, $policy]) {
		$file = parse($code);
		stmts($file)[$index]->remove($policy);
		Assert::same($html($code), $html((string) $file), (string) $file);
	}

	$file = parse("x\n<?php // c\n1;\n?>\nhtml");
	stmts($file)[1]->remove(CommentPolicy::MoveToPreviousToken);
	Assert::same("x\n<?php // c\n?>\nhtml", (string) $file);
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


test('a comment sharing the line of a removed node stands on a line of its own, or before the next token within a line', function () {
	$file = parse("<?php\n/* lead */ \$b;\n\$c;\n");
	stmts($file)[0]->remove();
	Assert::same("<?php\n/* lead */\n\$c;\n", (string) $file);

	$file = parse("<?php\n\t/* lead */ \$b; // b\n\t\$c;\n");
	stmts($file)[0]->remove();
	Assert::same("<?php\n\t/* lead */\n\t// b\n\t\$c;\n", (string) $file);

	$file = parse('<?php $a; $b /* x */; $c;');
	stmts($file)[1]->remove();
	Assert::same('<?php $a;  /* x */ $c;', (string) $file);
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
	assertAsReparsed($file);

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


test('remove leaves every token with the trivia the parser gives it in the printed text', function () {
	$statement = fn(int $i) => fn(FileNode $file) => stmts($file)[$i];
	$item = fn(int $i) => fn(FileNode $file) => ($file->findFirst(ArrayNode::class) ?? throw new LogicException)->items[$i];
	$next = CommentPolicy::MoveToNextToken;
	$previous = CommentPolicy::MoveToPreviousToken;
	$cases = [
		// an inline comment moved after the previous item is set apart from it
		["<?php \$x = [\n\t1,\n\t2 // two\n];\n", $item(1), $previous, "<?php \$x = [\n\t1 // two\n];\n"],
		["<?php \$x = [\n\t1,\n\t2 // two\n];\n", $item(1), $next, "<?php \$x = [\n\t1 // two\n];\n"],
		// a comment on a line of its own before a separator keeps the indentation of its line, the next item takes the one of the node
		["<?php \$x = [\n\t1\n\t// c\n\t, 2,\n];\n", $item(0), $next, "<?php \$x = [\n\t// c\n\t2,\n];\n"],
		["<?php \$x = [\n\t1\n\t// c\n\t, 2,\n];\n", $item(0), $previous, "<?php \$x = [\n\t// c\n\t2,\n];\n"],
		// the space before a comment goes with it
		['<?php $a; /* c */ $b;', $statement(0), $next, '<?php /* c */ $b;'],
		["<?php\n\$x;\n\$a; /* c */ \$b;\n", $statement(1), $previous, "<?php\n\$x;\n/* c */ \$b;\n"],
		// the comment close to the previous token with the gap below it, or close to the next one with the gap above it
		["<?php\n\$x;\n\n\$a; // c\n\n\$b;\n", $statement(1), $previous, "<?php\n\$x;\n// c\n\n\n\$b;\n"],
		["<?php\n\$x;\n\n\$a; // c\n\n\$b;\n", $statement(1), $next, "<?php\n\$x;\n\n// c\n\n\$b;\n"],
		["<?php\n\$x;\n\$a /* in */;\n\$b;\n", $statement(1), $previous, "<?php\n\$x;\n/* in */\n\$b;\n"],
		['<?php $a; $b /* x */; $c;', $statement(1), $next, '<?php $a;  /* x */ $c;'],
		[
			"<?php\nif (1) {\n\t\$x;\n\t\$a; // c\n\t\$b;\n}\n",
			fn(FileNode $file) => $file->find(ExpressionStatementNode::class)[1],
			$previous,
			"<?php\nif (1) {\n\t\$x;\n\t// c\n\t\$b;\n}\n",
		],
	];
	foreach ($cases as [$code, $node, $policy, $expected]) {
		$file = parse($code);
		$node($file)->remove($policy);
		Assert::same($expected, (string) $file);
		assertAsReparsed($file);
	}
});


test('a comment that ended the line of a removed node keeps its indentation on the line it gets', function () {
	$code = "<?php\n\$config = [\n\t'driver' => 1,\n\t'host' => 2, // the host\n\t'port' => 3,\n];\n";
	$expected = "<?php\n\$config = [\n\t'driver' => 1,\n\t// the host\n\t'port' => 3,\n];\n";
	foreach ([CommentPolicy::MoveToNextToken, CommentPolicy::MoveToPreviousToken] as $policy) {
		$file = parse($code);
		$array = $file->findFirst(ArrayNode::class) ?? throw new LogicException('No array.');
		$array->items[1]->remove($policy);
		Assert::same($expected, (string) $file);
	}

	$file = parse("<?php\nfunction f()\n{\n\t\$a = 1; // note\n\t\$b = 2;\n}\n");
	$file->find(ExpressionStatementNode::class)[0]->remove();
	Assert::same("<?php\nfunction f()\n{\n\t// note\n\t\$b = 2;\n}\n", (string) $file);
});


test('a refused write leaves the tree as it stood', function () {
	// the type of the slot decides before the trivia around the node move
	$file = parse('<?php /* keep */ $a;');
	$stmt = stmts($file)[0];
	Assert::type(ExpressionStatementNode::class, $stmt);
	Assert::exception(
		fn() => $stmt->expression->replaceWith((new Builder)->statement('return;')),
		InvalidArgumentException::class,
		'`PhpSyntax\\Nodes\\Statement\\ReturnNode` cannot be placed in the slot `expression` of `PhpSyntax\\Nodes\\Statement\\ExpressionStatementNode`.',
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
			'The node already belongs to a tree, `clone` it first.',
		);
		Assert::same($items, $first->parent);
		Assert::same([$first, $second], $items->getItems());
		Assert::same('<?php f($a, $b);', (string) $file);
	}

	// an item already in the list is refused before it takes the line ending of its neighbor
	$file = parse("<?php\n\$a;\n\$b;\n");
	Assert::exception(
		fn() => $file->statements->append($file->statements[1]),
		LogicException::class,
		'The node already belongs to a tree, `clone` it first.',
	);
	Assert::same("<?php\n\$a;\n\$b;\n", (string) $file);
});


test('an insertion of an item and a separator checks both before either moves', function () {
	$builder = new Builder;
	$file = parse('<?php f($a, $b);');
	$call = $file->findFirst(FunctionCallNode::class);
	Assert::type(FunctionCallNode::class, $call);
	$items = $call->arguments->items;

	// the separator stands in the list already, so the item stays in the fragment it came from
	$fragment = $builder->expression('g($c)');
	Assert::type(FunctionCallNode::class, $fragment);
	$item = $fragment->arguments->items[0];
	Assert::exception(
		fn() => $items->append($item, $items->getSeparators()[0]),
		LogicException::class,
		'The node already belongs to a tree, `clone` it first.',
	);
	Assert::same($fragment->arguments->items, $item->parent);
	Assert::same('g($c)', (string) $fragment);

	// a separator taken out of the item itself would stand in two lists at once
	$fragment = $builder->expression('g([$c, $d])');
	Assert::type(FunctionCallNode::class, $fragment);
	$item = $fragment->arguments->items[0];
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
	$node = (new Builder)->expression('($a)');
	Assert::type(ParenthesizedNode::class, $node);
	Assert::exception(
		fn() => $node->expression = $node,
		LogicException::class,
		'A node cannot be placed inside itself or inside what it holds.',
	);
	Assert::same('($a)', (string) $node);
	Assert::null($node->parent);

	// not even into an empty slot of a node standing nowhere, which is written the way a new node is built
	$yield = (new Builder)->expression('yield');
	Assert::type(PhpSyntax\Nodes\Expression\YieldNode::class, $yield);
	Assert::exception(
		fn() => $yield->value = $yield,
		LogicException::class,
		'A node cannot be placed inside itself or inside what it holds.',
	);
	Assert::null($yield->value);

	// nor into a list standing nowhere, which the parser fills the same way
	$list = new PlainNodeList([]);
	Assert::exception(
		fn() => $list->append($list),
		LogicException::class,
		'A node cannot be placed inside itself or inside what it holds.',
	);
	Assert::count(0, $list);

	$list = new SeparatedNodeList([]);
	Assert::exception(
		fn() => $list->append($list),
		LogicException::class,
		'A node cannot be placed inside itself or inside what it holds.',
	);
	Assert::count(0, $list);

	// nor may what holds it be written below it
	$file = parse('<?php f($a);');
	$call = $file->findFirst(FunctionCallNode::class);
	Assert::type(FunctionCallNode::class, $call);
	$argument = $call->arguments->items[0];
	Assert::type(ArgumentNode::class, $argument);
	Assert::exception(
		fn() => $argument->value = $call,
		LogicException::class,
		'A node cannot be placed inside itself or inside what it holds.',
	);
	Assert::same('<?php f($a);', (string) $file);

	// not even the root of a fragment, which stands nowhere itself
	$call = (new Builder)->expression('f($a)');
	Assert::type(FunctionCallNode::class, $call);
	$argument = $call->arguments->items[0];
	Assert::type(ArgumentNode::class, $argument);
	Assert::exception(
		fn() => $argument->value = $call,
		LogicException::class,
		'A node cannot be placed inside itself or inside what it holds.',
	);
	Assert::same('f($a)', (string) $call);
	Assert::null($call->parent);
});


test('a child that does not fit the slot is refused', function () {
	$node = (new Builder)->expression('($a)');
	Assert::type(ParenthesizedNode::class, $node);
	Assert::exception(
		fn() => $node->replaceChild($node->expression, new PhpSyntax\Token(1, 'x')),
		InvalidArgumentException::class,
		'Token `x` cannot be placed in the slot `expression` of `PhpSyntax\\Nodes\\Expression\\ParenthesizedNode`.',
	);
	Assert::same('($a)', (string) $node); // a refused write leaves the node as it was
});


test('remove is only for list items', function () {
	$stmt = stmts(parse('<?php $a;'))[0];
	Assert::type(ExpressionStatementNode::class, $stmt);
	Assert::exception($stmt->expression->remove(...), LogicException::class, 'Only an item of a list can be removed; a slot is emptied by its setter.');
	Assert::exception(fn() => new VariableNode(null, null, new PhpSyntax\Token(1, 'x'), null)->replaceWith($stmt->expression), LogicException::class, 'A node without a parent cannot be replaced.');
});
