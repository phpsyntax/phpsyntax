<?php declare(strict_types=1);

use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use PhpSyntax\Parser;
use PhpSyntax\TriviaKind;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


function parseStatement(string $code): ExpressionStatementNode
{
	$stmt = (new Parser)->parse("<?php\n$code")->statements->getItems()[0];
	assert($stmt instanceof ExpressionStatementNode);
	return $stmt;
}


test('$text is the node without the trivia on its edges, getTokens() the tokens under it', function () {
	$file = (new Parser)->parse("<?php\n\n// note\n\$a = f( 1, /* x */ 2 ); // tail\n");
	$stmt = $file->statements->getItems()[0];
	Assert::same('$a = f( 1, /* x */ 2 );', $stmt->text);
	Assert::same("<?php\n\n// note\n\$a = f( 1, /* x */ 2 ); // tail\n", (string) $file);
	// the file is a node with edges too, and the open tag is the leading trivia of its first token
	Assert::same("\$a = f( 1, /* x */ 2 ); // tail\n", $file->text);
	Assert::same(['$a', '=', 'f', '(', '1', ',', '2', ')', ';', ''], array_map(fn($token) => $token->text, $file->getTokens()));

	$empty = (new PhpSyntax\Nodes\NodeList);
	Assert::same('', $empty->text);
	Assert::same([], $empty->getTokens());

	// a heredoc keeps the text of its body, whitespace and all
	$heredoc = (new Parser)->parseExpression("<<<EOT\n\tx\n\tEOT");
	Assert::same("<<<EOT\n\tx\n\tEOT", $heredoc->text);
});


test('the trivia on the edges of a node are read where setEdgeTrivia() writes them', function () {
	$file = (new Parser)->parse("<?php\n\n// note\n\$a = 1; // tail\n");
	$stmt = $file->statements->getItems()[0];
	Assert::same(["<?php\n", "\n", '// note', "\n"], array_map(fn($trivia) => $trivia->text, $stmt->leadingTrivia));
	Assert::same([' ', '// tail', "\n"], array_map(fn($trivia) => $trivia->text, $stmt->trailingTrivia));
	Assert::same($stmt->getFirstToken()?->leadingTrivia, $stmt->leadingTrivia);

	$stmt->setEdgeTrivia([], []);
	Assert::same([], $stmt->leadingTrivia);
	Assert::same([], $stmt->trailingTrivia);

	$empty = (new PhpSyntax\Nodes\NodeList);
	Assert::same([], $empty->leadingTrivia);
	Assert::same([], $empty->trailingTrivia);
});


test('hasComment() sees comments inside the node, not on its edges', function () {
	Assert::false(parseStatement("/* a */ f(1); // b\n")->hasComment());
	Assert::true(parseStatement("f(/* a */ 1);\n")->hasComment());
	Assert::true(parseStatement("f(\n\t1, // a\n);\n")->hasComment());
	Assert::true(parseStatement("f(1) /* a */;\n")->hasComment());
	Assert::false(parseStatement("f(1)\n\t;\n")->hasComment());

	// the node walks its own tokens, so a subtree taken out of the tree answers as it did inside it
	$detached = clone parseStatement("f(/* a */ 1);\n");
	Assert::true($detached->hasComment());
	Assert::same(['/* a */'], array_map(fn($trivia) => $trivia->text, $detached->getComments()));
	Assert::true((new Parser)->parseExpression('f(/* a */ 1)')->hasComment());
});


test('a comment found on a node is removed and replaced through it', function () {
	$file = (new Parser)->parse("<?php\nclass A\n{\n\t/** doc */\n\tpublic function f()\n\t{\n\t\t// note\n\t\treturn 1;\n\t}\n}\n");
	$method = $file->find(MethodNode::class)[0];

	$note = $method->getComments()[0];
	Assert::same('// note', $note->text);
	$method->replaceTrivia($note, new PhpSyntax\Trivia(TriviaKind::Comment, '// kept'));
	Assert::same('// kept', $method->getComments()[0]->text);
	$method->removeTrivia($method->getComments()[0]);
	Assert::same([], $method->getComments());
	Assert::same("<?php\nclass A\n{\n\t/** doc */\n\tpublic function f()\n\t{\n\t\treturn 1;\n\t}\n}\n", (string) $file);

	// the doc comment stands on the edge of the node, which the comment queries leave out, and is reached too
	$doc = $method->getDocComment();
	Assert::type(PhpSyntax\Trivia::class, $doc);
	$method->removeTrivia($doc);
	Assert::null($method->getDocComment());
	Assert::same("<?php\nclass A\n{\n\tpublic function f()\n\t{\n\t\treturn 1;\n\t}\n}\n", (string) $file);

	Assert::exception(
		fn() => $method->removeTrivia(new PhpSyntax\Trivia(TriviaKind::Comment, '// alien')),
		LogicException::class,
		'The trivia does not belong to the node.',
	);
});


test('find() takes a class and a predicate, findFirst() stops at the first match', function () {
	$file = (new Parser)->parse('<?php f(1, 2); g(3);');
	$calls = $file->find(PhpSyntax\Nodes\Expression\FunctionCallNode::class);
	Assert::count(2, $calls);
	Assert::same(['1', '2', '3'], array_map(
		fn(PhpSyntax\Node $node) => $node->text,
		$file->find(PhpSyntax\Nodes\Scalar\IntegerNode::class),
	));

	// the predicate keeps the type of the class, so a slot of it is reached without instanceof
	Assert::same(['g'], array_map(
		fn(PhpSyntax\Nodes\Expression\FunctionCallNode $call) => $call->name->text,
		$file->find(
			PhpSyntax\Nodes\Expression\FunctionCallNode::class,
			fn(PhpSyntax\Nodes\Expression\FunctionCallNode $call) => count($call->arguments->items) === 1,
		),
	));

	Assert::same($calls[0], $file->findFirst(PhpSyntax\Nodes\Expression\FunctionCallNode::class));
	Assert::same('2', $file->findFirst(PhpSyntax\Nodes\Scalar\IntegerNode::class, fn($node) => $node->value === 2)?->text);
	Assert::null($file->findFirst(PhpSyntax\Nodes\Statement\ClassNode::class));
	Assert::null($file->findFirst(PhpSyntax\Node::class, fn() => false));

	// an interface every kind of declaration implements is a filter like any class
	$classes = (new Parser)->parse('<?php class A {} interface B {} $x = new class {};');
	Assert::count(3, $classes->find(PhpSyntax\Nodes\ClassLikeNode::class));
	Assert::type(
		PhpSyntax\Nodes\Statement\InterfaceNode::class,
		$classes->findFirst(PhpSyntax\Nodes\ClassLikeNode::class, fn($node) => !$node instanceof PhpSyntax\Nodes\Statement\ClassNode),
	);
});


test('getComments() sees the comments hasComment() counts', function () {
	$statement = parseStatement("/* a */ f(/* b */ 1); // c\n");
	Assert::same(['/* b */'], array_map(fn(PhpSyntax\Trivia $t) => $t->text, $statement->getComments()));
	$first = $statement->getFirstToken();
	$last = $statement->getLastToken();
	assert($first !== null && $last !== null);
	Assert::same(['/* a */', '// c'], array_map(
		fn(PhpSyntax\Trivia $t) => $t->text,
		[...$first->getComments(), ...$last->getComments()],
	));
	Assert::same([], parseStatement("f(1);\n")->getComments());
});


test('what a trivia says about a comment', function () {
	$file = (new Parser)->parse("<?php\n// a\n# b\n/* c */\n/**\n * d\n * e\n */\nf();\n");
	$comments = [];
	foreach ($file->getIndex()->getTokens() as $token) {
		$comments = [...$comments, ...$token->getComments()];
	}

	[$line, $hash, $block, $doc] = $comments;
	Assert::true($line->isLineComment());
	Assert::true($hash->isLineComment());
	Assert::false($block->isLineComment());
	Assert::false($doc->isLineComment());
	Assert::true($doc->isDocComment());
	Assert::false($block->isDocComment());
	Assert::false($line->isMultiLine());
	Assert::true($doc->isMultiLine());
	Assert::same(['a', 'b', 'c', "d\ne"], array_map(fn(PhpSyntax\Trivia $t) => $t->getCommentText(), $comments));

	// the tokenizer counts the spaces before the line break as part of a // comment; the text of it has none
	$spaced = (new Parser)->parse("<?php\nf(); // a  \n")->getIndex()->getTokens();
	Assert::same('a', $spaced[3]->getComments()[0]->getCommentText());
});


test('matches() compares token texts, not whitespace', function () {
	Assert::true(parseStatement("\$a[1] = 1;\n")->expression->matches(parseStatement("\$a [ 1 ]  =\n1;\n")->expression));
	Assert::false(parseStatement("\$a[1] = 1;\n")->expression->matches(parseStatement("\$a[2] = 1;\n")->expression));
});


test('$plainName is the name without the dollar, and null where the name is an expression', function () {
	$variable = parseStatement("\$a;\n")->expression;
	assert($variable instanceof PhpSyntax\Nodes\Expression\VariableNode);
	Assert::same('a', $variable->plainName);

	// the name of a static property is written as a variable but is not one
	$fetch = parseStatement("A::\$b;\n")->expression;
	assert($fetch instanceof PhpSyntax\Nodes\Expression\StaticPropertyFetchNode);
	Assert::same('b', $fetch->plainName);
	Assert::same([], $fetch->find(PhpSyntax\Nodes\Expression\VariableNode::class));

	// A::$$b names the property the variable $b holds, so that one is a variable again
	$dynamic = parseStatement("A::\$\$b;\n")->expression;
	assert($dynamic instanceof PhpSyntax\Nodes\Expression\StaticPropertyFetchNode);
	Assert::null($dynamic->plainName);
	Assert::same('b', $dynamic->find(PhpSyntax\Nodes\Expression\VariableNode::class)[0]->plainName);

	$braced = parseStatement("A::\${'b'};\n")->expression;
	assert($braced instanceof PhpSyntax\Nodes\Expression\StaticPropertyFetchNode);
	Assert::null($braced->plainName);
});


test('destructuring is a ListNode however it is written, an array literal is not', function () {
	$target = function (string $code): PhpSyntax\Nodes\Expression\ListNode {
		$assign = parseStatement($code)->expression;
		assert($assign instanceof PhpSyntax\Nodes\Expression\AssignmentNode);
		assert($assign->target instanceof PhpSyntax\Nodes\Expression\ListNode);
		return $assign->target;
	};
	$second = function (PhpSyntax\Nodes\Expression\ListNode $list): PhpSyntax\Nodes\ExpressionNode|PhpSyntax\Nodes\Expression\ListNode {
		$item = $list->items->getItems()[1];
		assert($item instanceof PhpSyntax\Nodes\ArrayItemNode);
		return $item->value;
	};

	// the short form has no keyword, and what nests in a target is a target too, either way round
	$short = $target("[\$a, [\$b]] = \$x;\n");
	Assert::null($short->listKeyword);
	Assert::same('[', $short->openDelimiter->text);
	Assert::type(PhpSyntax\Nodes\Expression\ListNode::class, $second($short));

	$long = $target("list(\$a, [\$b]) = \$x;\n");
	Assert::same('list', $long->listKeyword?->text);
	Assert::type(PhpSyntax\Nodes\Expression\ListNode::class, $second($long));

	// nothing of that happens where the array is a value
	$value = parseStatement("\$x = [\$a, [\$b]];\n")->expression;
	assert($value instanceof PhpSyntax\Nodes\Expression\AssignmentNode);
	Assert::type(PhpSyntax\Nodes\Expression\ArrayNode::class, $value->expression);
	Assert::type(PhpSyntax\Nodes\Expression\ArrayNode::class, parseStatement("[1, [2]];\n")->expression);
});


test('the value an expression is written as', function () {
	$value = fn(string $code) => (new Parser)->parseExpression($code)->toValue();
	Assert::same(1, $value('1'));
	Assert::same(1.5, $value('1.5'));
	Assert::same('a', $value("'a'"));
	Assert::same("b\n", $value('"b\n"'));
	Assert::same([true, false, null], [$value('true'), $value('FALSE'), $value('null')]);
	Assert::same(-1, $value('-1'));
	Assert::same(2.5, $value('+2.5'));
	Assert::same(1, $value('(1)'));
	Assert::same('ab', $value("<<<TXT\n\tab\n\tTXT"));
	Assert::same(['a' => 1, 'b' => [2, 3]], $value("['a' => 1, 'b' => [2, 3]]"));
	Assert::same([1, 2, 3], $value('[1, ...[2, 3]]'));
	// spreading gives its own numeric items new keys and keeps the string ones, the keys already there untouched
	Assert::same([5 => 'a', 6 => 'b'], $value('[5 => "a", ...["b"]]'));
	Assert::same(['k' => 2, 0 => 1], $value('["k" => 1, ...[1, "k" => 2]]'));

	// what a name stands for depends on what the code around it defines, so it is no value here
	foreach (['PHP_EOL', 'self::FOO', '$a', '1 + 2', '-PHP_INT_MAX', 'f()', '[&$a]', '"x{$a}"'] as $code) {
		Assert::false((new Parser)->parseExpression($code)->hasValue(), $code);
		Assert::exception(
			fn() => $value($code),
			LogicException::class,
			'The expression has no value of its own: ' . $code,
		);
	}

	// what is written inside says as much as what is written around it, and says it the same way
	Assert::false((new Parser)->parseExpression('["x{$a}"]')->hasValue());
	Assert::exception(
		fn() => $value('["x{$a}"]'),
		LogicException::class,
		'The expression has no value of its own: ["x{$a}"]',
	);

	Assert::true((new Parser)->parseExpression('[1, null]')->hasValue());
	Assert::null($value('null')); // the value null is told apart from no value
});


test('isRepeatableRead()', function () {
	Assert::true(parseStatement("\$a->b[C::D];\n")->expression->isRepeatableRead());
	Assert::false(parseStatement("\$a->b();\n")->expression->isRepeatableRead());
	Assert::false(parseStatement("\$a[f()];\n")->expression->isRepeatableRead());

	// every literal counts, whatever it is written with, and a string is worth what its pieces are
	Assert::true(parseStatement("__LINE__;\n")->expression->isRepeatableRead());
	Assert::true(parseStatement("\"a\$b\";\n")->expression->isRepeatableRead());
	Assert::true(parseStatement("<<<X\n\ta\n\tX;\n")->expression->isRepeatableRead());
	Assert::false(parseStatement("\"a{\$b->c()}\";\n")->expression->isRepeatableRead());
});
