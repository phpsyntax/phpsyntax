<?php declare(strict_types=1);

use PhpSyntax\{Node, Parser};
use PhpSyntax\Nodes\{ArgumentListNode, ExpressionNode, FileNode, NameNode};
use PhpSyntax\Nodes\Expression\{BinaryOpNode, CombinedAssignmentNode, FunctionCallNode, MethodCallNode, NewNode, ParenthesizedNode, PropertyFetchNode, StaticMethodCallNode};
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


function expression(string $code): ExpressionNode
{
	return (new Parser)->parseExpression($code);
}


/**
 * @template T of Node
 * @param  class-string<T>  $class
 * @return T
 */
function findNode(FileNode|string $in, string $class, ?string $text = null): Node
{
	$file = $in instanceof FileNode ? $in : (new Parser)->parse($in);
	$node = $file->findFirst($class, fn(Node $node) => $text === null || $node->text === $text);
	return $node ?? throw new LogicException("No $class in the code.");
}


// the text a factory wrote parses to a tree of the same shape, so the tokens it made are the ones the lexer makes
function assertParsesBack(ExpressionNode $node): void
{
	$parsed = expression((string) $node);
	Assert::type($node::class, $parsed);
	Assert::same(
		array_map(fn($token) => [$token->kind, $token->text], $parsed->getTokens()),
		array_map(fn($token) => [$token->kind, $token->text], $node->getTokens()),
	);
}


test('an argument list of values is written on one line', function () {
	Assert::same('()', (string) ArgumentListNode::of());
	Assert::same('($a)', (string) ArgumentListNode::of(expression('$a')));
	Assert::same('($a, f(1), [2])', (string) ArgumentListNode::of(expression('$a'), expression('f(1)'), expression('[2]')));
});


test('the values of a list lose the trivia on their edges and keep what is inside', function () {
	$value = clone findNode("<?php\nf( \$a /* c */ + 1 );\n", BinaryOpNode::class);
	Assert::same('($a /* c */ + 1)', (string) ArgumentListNode::of($value));
});


test('the calls are written as the parser reads them', function () {
	$call = FunctionCallNode::of(NameNode::fromText('\strlen'), ArgumentListNode::of(expression('$s')));
	Assert::same('\strlen($s)', (string) $call);
	assertParsesBack($call);

	$call = FunctionCallNode::of(expression('$callable'));
	Assert::same('$callable()', (string) $call);
	assertParsesBack($call);

	$call = MethodCallNode::of(expression('$this'), 'getName');
	Assert::same('$this->getName()', (string) $call);
	assertParsesBack($call);

	$call = MethodCallNode::of(expression('$a->b'), 'c', ArgumentListNode::of(expression('1')), nullsafe: true);
	Assert::same('$a->b?->c(1)', (string) $call);
	Assert::true($call->isNullsafe());
	assertParsesBack($call);

	$call = StaticMethodCallNode::of(NameNode::fromText('Foo\Bar'), 'create', ArgumentListNode::of(expression('$x'), expression('$y')));
	Assert::same('Foo\Bar::create($x, $y)', (string) $call);
	assertParsesBack($call);

	$call = StaticMethodCallNode::of(expression('$class'), 'create');
	Assert::same('$class::create()', (string) $call);
	assertParsesBack($call);

	$new = NewNode::of(NameNode::fromText('\Foo'), ArgumentListNode::of(expression('$x')));
	Assert::same('new \Foo($x)', (string) $new);
	assertParsesBack($new);

	$new = NewNode::of(NameNode::fromText('Foo'));
	Assert::same('new Foo', (string) $new);
	assertParsesBack($new);
});


test('what a call could not reach into bare stands in parentheses', function () {
	$call = FunctionCallNode::of(expression('$a->b'), ArgumentListNode::of(expression('1')));
	Assert::same('($a->b)(1)', (string) $call);
	assertParsesBack($call);
	Assert::same('$a[0]()', (string) FunctionCallNode::of(expression('$a[0]')));
	Assert::same("('strlen')()", (string) FunctionCallNode::of(expression("'strlen'")));

	$call = MethodCallNode::of(expression('new Foo'), 'bar');
	Assert::same('(new Foo)->bar()', (string) $call);
	assertParsesBack($call);
	Assert::same('($a ?? $b)->bar()', (string) MethodCallNode::of(expression('$a ?? $b'), 'bar'));
	Assert::same('f()->bar()', (string) MethodCallNode::of(expression('f()'), 'bar'));

	$call = StaticMethodCallNode::of(expression('$a ?: $b'), 'create');
	Assert::same('($a ?: $b)::create()', (string) $call);
	assertParsesBack($call);
	Assert::same('$a->b::create()', (string) StaticMethodCallNode::of(expression('$a->b'), 'create'));

	$new = NewNode::of(expression('f()'));
	Assert::same('new (f())', (string) $new);
	assertParsesBack($new);
	Assert::same('new $a->b', (string) NewNode::of(expression('$a->b')));
});


test('a binary operation puts an operand in parentheses where its side of the operator asks', function () {
	$operation = BinaryOpNode::of(expression('$a'), '??', expression('$b'));
	Assert::same('$a ?? $b', (string) $operation);
	assertParsesBack($operation);

	Assert::same('($a ?? $b) . $c', (string) BinaryOpNode::of(expression('$a ?? $b'), '.', expression('$c')));
	Assert::same('$a * $b + $c', (string) BinaryOpNode::of(expression('$a * $b'), '+', expression('$c')));
	Assert::same('$a - ($b - $c)', (string) BinaryOpNode::of(expression('$a'), '-', expression('$b - $c')));
	Assert::same('$a - $b - $c', (string) BinaryOpNode::of(expression('$a - $b'), '-', expression('$c')));
	Assert::same('$a ?? $b ?? $c', (string) BinaryOpNode::of(expression('$a'), '??', expression('$b ?? $c')));
	Assert::same('($a = 1) && $b', (string) BinaryOpNode::of(expression('$a = 1'), '&&', expression('$b')));
	Assert::same('$a && ($b = 1)', (string) BinaryOpNode::of(expression('$a'), '&&', expression('$b = 1')));
	Assert::same('$a === null', (string) BinaryOpNode::of(expression('$a'), '===', expression('null')));
	Assert::same('($a) . $b', (string) BinaryOpNode::of(expression('($a)'), '.', expression('$b'))); // the ones given stay

	$operation = BinaryOpNode::of(expression('$a'), 'and', expression('$b'));
	Assert::same('$a and $b', (string) $operation);
	assertParsesBack($operation);

	// the tokens the lexer would read together are kept apart
	Assert::same('$a - -$b', (string) BinaryOpNode::of(expression('$a'), '-', expression('-$b')));

	Assert::exception(
		fn() => BinaryOpNode::of(expression('$a'), '+ 1 +', expression('$b')),
		InvalidArgumentException::class,
		"'+ 1 +' is not a binary operator.",
	);
	Assert::exception(
		fn() => BinaryOpNode::of(expression('$a'), '=', expression('$b')),
		InvalidArgumentException::class,
		"'=' is not a binary operator.",
	);
});


test('a combined assignment puts its expression in parentheses where the assignment asks', function () {
	$assignment = CombinedAssignmentNode::of(expression('$total'), '+=', expression('$vat'));
	Assert::same('$total += $vat', (string) $assignment);
	assertParsesBack($assignment);

	$assignment = CombinedAssignmentNode::of(expression('$cart[\'sum\']'), '??=', expression('$a ?: $b'));
	Assert::same('$cart[\'sum\'] ??= $a ?: $b', (string) $assignment);
	assertParsesBack($assignment);

	Assert::same('$a .= ($b or $c)', (string) CombinedAssignmentNode::of(expression('$a'), '.=', expression('$b or $c')));
	Assert::same('$a *= $b = 2', (string) CombinedAssignmentNode::of(expression('$a'), '*=', expression('$b = 2')));

	Assert::exception(
		fn() => CombinedAssignmentNode::of(expression('$a'), '=', expression('$b')),
		InvalidArgumentException::class,
		"'=' is not a combined assignment operator.",
	);
	Assert::exception(
		fn() => CombinedAssignmentNode::of(expression('f()'), '+=', expression('$b')),
		InvalidArgumentException::class,
		"'f()' is no place to assign to.",
	);
});


test('a name that is no identifier is refused', function () {
	Assert::exception(
		fn() => MethodCallNode::of(expression('$a'), 'b c'),
		InvalidArgumentException::class,
		"'b c' is not an identifier.",
	);
});


test('a factory takes a list out of a tree without a file and refuses one of a live tree', function () {
	$old = expression('$o->old( $a, $b )');
	assert($old instanceof MethodCallNode);
	$call = FunctionCallNode::of(NameNode::fromText('f'), $old->arguments);
	Assert::same('f( $a, $b )', (string) $call);

	$live = findNode("<?php\n\$o->old(\$a);\n", MethodCallNode::class);
	Assert::exception(
		fn() => FunctionCallNode::of(NameNode::fromText('f'), $live->arguments),
		LogicException::class,
		'The node already belongs to a tree; a copy comes from withoutEdgeTrivia(), or from clone with the trivia on its edges.',
	);
});


test('a factory refusing a node of a live tree leaves the tree as it was', function () {
	$code = "<?php\nfunction f() {\n\t/* keep */ \$x->y ;\n\tg( \$z ) ;\n}\n";
	$file = (new Parser)->parse($code);
	$live = findNode($file, PropertyFetchNode::class);
	$factories = [
		fn() => ArgumentListNode::of(expression('$a'), $live),
		fn() => FunctionCallNode::of($live),
		fn() => FunctionCallNode::of(NameNode::fromText('f'), findNode($file, FunctionCallNode::class)->arguments),
		fn() => MethodCallNode::of($live, 'm'),
		fn() => StaticMethodCallNode::of($live, 'm'),
		fn() => NewNode::of($live),
		fn() => BinaryOpNode::of($live, '+', expression('1')),
		fn() => ParenthesizedNode::of($live),
	];
	foreach ($factories as $factory) {
		Assert::exception(
			$factory,
			LogicException::class,
			'The node already belongs to a tree; a copy comes from withoutEdgeTrivia(), or from clone with the trivia on its edges.',
		);
		Assert::same($code, (string) $file);
	}
});


test('a factory refusing its input leaves the tree without a file it takes the other nodes from as it was', function () {
	$old = expression('$o /* c */ ->old( $a )');
	assert($old instanceof MethodCallNode);
	$object = $old->object;
	$live = findNode("<?php\n\$x->y;\n", PropertyFetchNode::class);
	$liveArguments = findNode("<?php\nf(1);\n", ArgumentListNode::class);
	$factories = [
		fn() => ArgumentListNode::of($object, $live),
		fn() => FunctionCallNode::of($object, $liveArguments),
		fn() => MethodCallNode::of($object, 'm', $liveArguments),
		fn() => StaticMethodCallNode::of($object, 'm', $liveArguments),
		fn() => NewNode::of($object, $liveArguments),
		fn() => MethodCallNode::of($object, 'b c'),
		fn() => StaticMethodCallNode::of($object, 'b c'),
		fn() => BinaryOpNode::of($object, '+', $live),
		fn() => BinaryOpNode::of($object, 'foo', expression('1')),
	];
	foreach ($factories as $factory) {
		Assert::exception($factory, Exception::class);
		Assert::same('$o /* c */ ->old( $a )', (string) $old);
		Assert::same($old, $object->parent);
	}
});


test('a node given twice, or inside another one given, is refused before anything moves', function () {
	$old = expression('$o->old($a)');
	assert($old instanceof MethodCallNode);
	$object = $old->object;
	$message = 'A node cannot be two parts of the node a factory builds; a copy comes from withoutEdgeTrivia().';
	Assert::exception(fn() => ArgumentListNode::of($object, $object), LogicException::class, $message);
	Assert::exception(fn() => BinaryOpNode::of($object, '+', $object), LogicException::class, $message);
	Assert::exception(fn() => MethodCallNode::of($old, 'm', $old->arguments), LogicException::class, $message);
	Assert::same('$o->old($a)', (string) $old);
	Assert::same($old, $object->parent);
});


test('what a call is made of loses the trivia on its edges', function () {
	$old = clone findNode("<?php\n\$o /* c */ ->old(\$a) ;\n", MethodCallNode::class);
	$call = MethodCallNode::of($old->object, 'renamed', $old->arguments);
	Assert::same('$o->renamed($a)', (string) $call);
});


test('parentheses are a factory like the others: the expression loses the trivia on its edges', function () {
	$sum = clone findNode("<?php\nf( \$a + \$b /* c */ );\n", BinaryOpNode::class);
	Assert::same('($a + $b)', (string) ParenthesizedNode::of($sum));
});
