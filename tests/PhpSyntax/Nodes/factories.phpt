<?php declare(strict_types=1);

use PhpSyntax\Node;
use PhpSyntax\Nodes\ArgumentListNode;
use PhpSyntax\Nodes\Expression\BinaryOpNode;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Expression\MethodCallNode;
use PhpSyntax\Nodes\Expression\NewNode;
use PhpSyntax\Nodes\Expression\ParenthesizedNode;
use PhpSyntax\Nodes\Expression\StaticMethodCallNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Parser;
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
		"'+ 1 +' is no binary operator.",
	);
	Assert::exception(
		fn() => BinaryOpNode::of(expression('$a'), '=', expression('$b')),
		InvalidArgumentException::class,
		"'=' is no binary operator.",
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
		'The node already belongs to a tree, write a copy of it: withoutEdgeTrivia(), or a clone to keep the trivia on its edges.',
	);
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
