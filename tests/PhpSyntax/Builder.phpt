<?php declare(strict_types=1);

use PhpSyntax\{Builder, Node, Parser};
use PhpSyntax\Nodes\{ArgumentNode, ArrayItemNode, DestructuringNode, ExpressionNode, FileNode, IdentifierNode, NameNode};
use PhpSyntax\Nodes\Expression\{ArrayNode, AssignmentNode, BinaryOpNode, MethodCallNode, PropertyFetchNode, UnaryOpNode, VariableNode};
use PhpSyntax\Nodes\Scalar\{FloatNode, IntegerNode, StringNode};
use PhpSyntax\Nodes\Statement\{ExpressionStatementNode, ForeachNode, ReturnNode};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function expression(string $code): ExpressionNode
{
	return (new Builder)->expression($code);
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


// the text the builder wrote parses to a tree of the same shape, so the tokens it made are the ones the lexer makes
function assertParsesBack(ExpressionNode $node): void
{
	$parsed = expression((string) $node);
	Assert::type($node::class, $parsed);
	Assert::same(
		array_map(fn($token) => [$token->id, $token->text], $parsed->getTokens()),
		array_map(fn($token) => [$token->id, $token->text], $node->getTokens()),
	);
}


$b = new Builder;


test('values are written as literals that read back as the same values', function () use ($b) {
	Assert::type(IntegerNode::class, $b->value(42));
	Assert::same('-5', (string) $b->value(-5));
	Assert::type(UnaryOpNode::class, $b->value(-5));
	Assert::same('-9223372036854775807-1', (string) $b->value(PHP_INT_MIN));
	Assert::type(FloatNode::class, $b->value(1.0));
	Assert::same('1.0', (string) $b->value(1.0));
	Assert::same(0.1, $b->value(0.1)->toValue());
	Assert::same(1e100, $b->value(1e100)->toValue());
	Assert::same('INF', (string) $b->value(INF));
	Assert::same('true', (string) $b->value(true));
	Assert::same('false', (string) $b->value(false));
	Assert::same('null', (string) $b->value(null));
	Assert::type(StringNode::class, $b->value("it's"));
	Assert::same("'it\\'s'", (string) $b->value("it's"));
	Assert::same('[1, 2, 3]', (string) $b->value([1, 2, 3]));
	Assert::same("[1 => 'a', 'b' => [true, null]]", (string) $b->value([1 => 'a', 'b' => [true, null]]));
	Assert::same([1 => 'a', 'b' => [true, null]], $b->value([1 => 'a', 'b' => [true, null]])->toValue());
	Assert::same('[]', (string) $b->value([]));
	Assert::same('[$a + 1, 2]', (string) $b->value([expression('$a + 1'), 2]));

	Assert::exception(fn() => $b->value(new stdClass), InvalidArgumentException::class, 'Value of type `stdClass` cannot be written as an expression.');
	Assert::exception(fn() => $b->value(NameNode::fromText('A')), InvalidArgumentException::class, '`PhpSyntax\Nodes\NameNode` is not an expression.');
});


test('a node standing in a tree is copied without its edges, a detached one taken as it is', function () use ($b) {
	$file = (new Parser)->parse("<?php\nf( /* a */ \$x /* b */ );\n");
	$live = findNode($file, VariableNode::class);
	$value = $b->value($live);
	Assert::notSame($live, $value);
	Assert::same('$x', (string) $value);
	Assert::same("<?php\nf( /* a */ \$x /* b */ );\n", (string) $file);

	$detached = expression('$y');
	$detached->setEdgeTrivia([new PhpSyntax\Trivia(PhpSyntax\Trivia::Whitespace, ' ')], []);
	Assert::same($detached, $b->value($detached));
	Assert::same('$y', (string) $detached);

	// a node of a subtree without a file stands in a tree too, which keeps it
	$old = expression('$o->old($a)');
	assert($old instanceof MethodCallNode);
	$call = $b->call('f', [$old->object]);
	Assert::same('f($o)', (string) $call);
	Assert::same('$o->old($a)', (string) $old);
});


test('a detached node given twice is refused before anything moves, a node in a tree comes in as a copy each time', function () use ($b) {
	$old = expression('$o->old($a)');
	assert($old instanceof MethodCallNode);
	$object = $old->object;
	$message = 'A node cannot be two parts of the node the builder builds; a copy comes from `withoutEdgeTrivia()`.';
	Assert::exception(fn() => $b->arguments([$old, $old]), LogicException::class, $message);
	Assert::exception(fn() => $b->binary($old, '+', $old), LogicException::class, $message);
	Assert::exception(fn() => $b->expression('$x + $y', x: $old, y: $old), LogicException::class, $message);
	Assert::same('$o->old($a)', (string) $old);
	Assert::null($old->parent);

	Assert::same('$o + $o', (string) $b->binary($object, '+', $object));
	Assert::same('$o->old($a)->m($a)', (string) $b->methodCall($old, 'm', [$old->arguments->items[0]]));
	Assert::same($old, $object->parent);
});


test('variables, constants and fetches', function () use ($b) {
	Assert::same('$a', (string) $b->variable('a'));
	Assert::same('$a', (string) $b->variable('$a'));
	Assert::exception(fn() => $b->variable('a b'), InvalidArgumentException::class, '`a b` is not a name of a variable.');
	Assert::exception(fn() => $b->constant('true'), InvalidArgumentException::class, '`true` is a literal, which `value()` writes.');
	Assert::same('\PHP_EOL', (string) $b->constant('\PHP_EOL'));
	Assert::same('FOO', (string) $b->constant(NameNode::fromText('FOO')));

	Assert::same('$a->b', (string) $b->propertyFetch(expression('$a'), 'b'));
	Assert::same('$a?->b', (string) $b->propertyFetch(expression('$a'), IdentifierNode::fromText('b'), nullsafe: true));
	Assert::same('$a->$name', (string) $b->propertyFetch(expression('$a'), expression('$name')));
	Assert::same('$a->{$x . $y}', (string) $b->propertyFetch(expression('$a'), expression('$x . $y')));
	Assert::same('(new A)->b', (string) $b->propertyFetch(expression('new A'), 'b'));
	assertParsesBack($b->propertyFetch(expression('new A'), 'b'));

	Assert::same('A::$b', (string) $b->staticPropertyFetch('A', 'b'));
	Assert::same('$c::$b', (string) $b->staticPropertyFetch(expression('$c'), '$b'));
	Assert::same('A::B', (string) $b->classConstantFetch('A', 'B'));
	Assert::same('A::{$name}', (string) $b->classConstantFetch(NameNode::fromText('A'), expression('$name')));
	Assert::same('($a ?: $b)::C', (string) $b->classConstantFetch(expression('$a ?: $b'), 'C'));
	assertParsesBack($b->classConstantFetch(expression('$a ?: $b'), 'C'));

	Assert::same('$a[0]', (string) $b->arrayAccess(expression('$a'), 0));
	Assert::same("\$a['k']", (string) $b->arrayAccess(expression('$a'), 'k'));
	Assert::same('$a[]', (string) $b->arrayAccess(expression('$a')));
	Assert::same('($a . $b)[$i]', (string) $b->arrayAccess(expression('$a . $b'), expression('$i')));
});


test('the calls are written as the parser reads them', function () use ($b) {
	$call = $b->call(NameNode::fromText('\strlen'), [expression('$s')]);
	Assert::same('\strlen($s)', (string) $call);
	assertParsesBack($call);

	$call = $b->call('\strlen', ['$s']);
	Assert::same("\\strlen('\$s')", (string) $call);
	Assert::type(NameNode::class, $call->name);
	Assert::exception(fn() => $b->call('$a'), InvalidArgumentException::class, '`$a` is not a name.');

	$call = $b->call(expression('$callable'));
	Assert::same('$callable()', (string) $call);
	assertParsesBack($call);

	$call = $b->methodCall(expression('$this'), 'getName');
	Assert::same('$this->getName()', (string) $call);
	assertParsesBack($call);

	$call = $b->methodCall(expression('$a->b'), 'c', [1], nullsafe: true);
	Assert::same('$a->b?->c(1)', (string) $call);
	Assert::true($call->nullsafe);
	assertParsesBack($call);

	Assert::same('$a->$m()', (string) $b->methodCall(expression('$a'), expression('$m')));
	Assert::same("\$a->{'m' . \$i}()", (string) $b->methodCall(expression('$a'), expression("'m' . \$i")));

	$call = $b->staticMethodCall('Foo\Bar', 'create', [expression('$x'), expression('$y')]);
	Assert::same('Foo\Bar::create($x, $y)', (string) $call);
	assertParsesBack($call);

	$call = $b->staticMethodCall(expression('$class'), 'create');
	Assert::same('$class::create()', (string) $call);
	assertParsesBack($call);

	$new = $b->new('\Foo', [expression('$x')]);
	Assert::same('new \Foo($x)', (string) $new);
	assertParsesBack($new);

	$new = $b->new(NameNode::fromText('Foo'));
	Assert::same('new Foo', (string) $new);
	assertParsesBack($new);

	Assert::exception(fn() => $b->methodCall(expression('$a'), 'b c'), InvalidArgumentException::class, '`b c` is not an identifier.');
});


test('what a call could not reach into bare stands in parentheses', function () use ($b) {
	$call = $b->call(expression('$a->b'), [1]);
	Assert::same('($a->b)(1)', (string) $call);
	assertParsesBack($call);
	Assert::same('$a[0]()', (string) $b->call(expression('$a[0]')));
	Assert::same("('strlen')()", (string) $b->call(expression("'strlen'")));

	$call = $b->methodCall(expression('new Foo'), 'bar');
	Assert::same('(new Foo)->bar()', (string) $call);
	assertParsesBack($call);
	Assert::same('($a ?? $b)->bar()', (string) $b->methodCall(expression('$a ?? $b'), 'bar'));
	Assert::same('f()->bar()', (string) $b->methodCall(expression('f()'), 'bar'));

	$call = $b->staticMethodCall(expression('$a ?: $b'), 'create');
	Assert::same('($a ?: $b)::create()', (string) $call);
	assertParsesBack($call);
	Assert::same('$a->b::create()', (string) $b->staticMethodCall(expression('$a->b'), 'create'));

	$new = $b->new(expression('f()'));
	Assert::same('new (f())', (string) $new);
	assertParsesBack($new);
	Assert::same('new $a->b', (string) $b->new(expression('$a->b')));
});


test('arguments: values, nodes, names and arguments as they are', function () use ($b) {
	Assert::same('()', (string) $b->arguments([]));
	Assert::same('($a)', (string) $b->arguments([expression('$a')]));
	Assert::same("(\$a, f(1), [2], 'x', 3)", (string) $b->arguments([expression('$a'), expression('f(1)'), expression('[2]'), 'x', 3]));
	Assert::same('(1, flags: 2)', (string) $b->arguments([1, 'flags' => 2]));
	$named = $b->call('f', ['flags' => 2]);
	Assert::same('flags', $named->arguments->findArgument('flags', null)?->name?->text);

	$argument = (new Builder)->fragment(ArgumentNode::class, '...$rest');
	Assert::same($argument, $b->arguments([$argument])->items[0]);
	Assert::same('(1, ...$rest)', (string) $b->arguments([1, $argument]));
	Assert::notSame($argument, $b->arguments([$argument])->items[0]); // it stands in a list now
	Assert::exception(fn() => $b->arguments(['a' => $argument]), InvalidArgumentException::class, 'Argument `a` is an `ArgumentNode`, which carries its own name.');
	Assert::exception(fn() => $b->arguments(['a b' => 1]), InvalidArgumentException::class, '`a b` is not an identifier.');

	// a list given is taken as it is, which is how a call is renamed keeping its arguments
	$old = expression('$o->old( $a, $b )');
	assert($old instanceof MethodCallNode);
	Assert::same('f( $a, $b )', (string) $b->call('f', $old->arguments));

	$value = clone findNode("<?php\nf( \$a /* c */ + 1 );\n", BinaryOpNode::class);
	Assert::same('($a /* c */ + 1)', (string) $b->arguments([$value]));
});


test('a binary operation puts an operand in parentheses where its side of the operator asks', function () use ($b) {
	$operation = $b->binary(expression('$a'), '??', expression('$b'));
	Assert::same('$a ?? $b', (string) $operation);
	assertParsesBack($operation);

	Assert::same('$a === 1', (string) $b->binary(expression('$a'), '===', 1));
	Assert::same('($a ?? $b) . $c', (string) $b->binary(expression('$a ?? $b'), '.', expression('$c')));
	Assert::same('$a * $b + $c', (string) $b->binary(expression('$a * $b'), '+', expression('$c')));
	Assert::same('$a - ($b - $c)', (string) $b->binary(expression('$a'), '-', expression('$b - $c')));
	Assert::same('$a - $b - $c', (string) $b->binary(expression('$a - $b'), '-', expression('$c')));
	Assert::same('$a ?? $b ?? $c', (string) $b->binary(expression('$a'), '??', expression('$b ?? $c')));
	Assert::same('($a = 1) && $b', (string) $b->binary(expression('$a = 1'), '&&', expression('$b')));
	Assert::same('$a && ($b = 1)', (string) $b->binary(expression('$a'), '&&', expression('$b = 1')));
	Assert::same('($a) . $b', (string) $b->binary(expression('($a)'), '.', expression('$b'))); // the ones given stay

	$operation = $b->binary(expression('$a'), 'and', expression('$b'));
	Assert::same('$a and $b', (string) $operation);
	assertParsesBack($operation);

	// the tokens the lexer would read together are kept apart
	Assert::same('$a - -$b', (string) $b->binary(expression('$a'), '-', expression('-$b')));

	Assert::exception(fn() => $b->binary(expression('$a'), '+ 1 +', expression('$b')), InvalidArgumentException::class, '`+ 1 +` is not a binary operator.');
	Assert::exception(fn() => $b->binary(expression('$a'), '=', expression('$b')), InvalidArgumentException::class, '`=` is not a binary operator.');
});


test('unary operators, casts, ternaries and parentheses', function () use ($b) {
	Assert::same('!$a', (string) $b->unary('!', expression('$a')));
	Assert::same('!($a && $b)', (string) $b->unary('!', expression('$a && $b')));
	Assert::same('-(-$a)', (string) $b->unary('-', expression('-$a')));
	Assert::same('@f()', (string) $b->unary('@', expression('f()')));
	Assert::exception(fn() => $b->unary('++', expression('$a')), InvalidArgumentException::class, '`++` is not a unary operator.');

	Assert::same('(int) $a', (string) $b->cast('int', expression('$a')));
	Assert::same('(string) ($a . $b)', (string) $b->cast('string', expression('$a . $b')));
	Assert::exception(fn() => $b->cast('foo', expression('$a')), InvalidArgumentException::class, '`foo` is not a type of a cast.');
	Assert::exception(fn() => $b->cast('int) $x . (int', expression('$a')), InvalidArgumentException::class, '`int) $x . (int` is not a type of a cast.');

	Assert::same('$a ? 1 : 2', (string) $b->ternary(expression('$a'), 1, 2));
	Assert::same('$a ?: $b', (string) $b->shortTernary(expression('$a'), expression('$b')));
	Assert::same('$a ? null : $b', (string) $b->ternary(expression('$a'), null, expression('$b')));
	Assert::same('($a ? 1 : 2) ? 3 : 4', (string) $b->ternary(expression('$a ? 1 : 2'), 3, 4));
	assertParsesBack($b->ternary(expression('$a ? 1 : 2'), 3, 4));

	$sum = clone findNode("<?php\nf( \$a + \$b /* c */ );\n", BinaryOpNode::class);
	Assert::same('($a + $b)', (string) $b->parenthesize($sum));
	Assert::same('(1)', (string) $b->parenthesize(1));
});


test('an assignment puts its expression in parentheses where the assignment asks', function () use ($b) {
	$assignment = $b->assign(expression('$total'), expression('$net + $vat'));
	Assert::same('$total = $net + $vat', (string) $assignment);
	assertParsesBack($assignment);
	Assert::same('$a = 1', (string) $b->assign(expression('$a'), 1));

	$destructuring = expression('[$a, $b] = $x');
	assert($destructuring instanceof AssignmentNode);
	$assignment = $b->assign($destructuring->target, expression('$pair'));
	Assert::same('[$a, $b] = $pair', (string) $assignment);
	assertParsesBack($assignment);

	// a short array destructures as it does in the code, nested ones included, a long one is no target
	$assignment = $b->assign(expression('[$a, [$b, $c]]'), expression('$rows'));
	Assert::same('[$a, [$b, $c]] = $rows', (string) $assignment);
	$target = $assignment->target;
	assert($target instanceof DestructuringNode);
	$nested = $target->items[1];
	assert($nested instanceof ArrayItemNode);
	Assert::type(DestructuringNode::class, $nested->value);
	assertParsesBack($assignment);
	Assert::exception(fn() => $b->assign(expression('array($a)'), expression('$b')), InvalidArgumentException::class, '`array($a)` is no place to assign to.');

	Assert::same('$a = ($b and $c)', (string) $b->assign(expression('$a'), expression('$b and $c')));
	Assert::exception(fn() => $b->assign(expression('A::B'), expression('$b')), InvalidArgumentException::class, '`A::B` is no place to assign to.');
});


test('a combined assignment puts its expression in parentheses where the assignment asks', function () use ($b) {
	$assignment = $b->combinedAssign(expression('$total'), '+=', expression('$vat'));
	Assert::same('$total += $vat', (string) $assignment);
	assertParsesBack($assignment);

	$assignment = $b->combinedAssign(expression('$cart[\'sum\']'), '??=', expression('$a ?: $b'));
	Assert::same('$cart[\'sum\'] ??= $a ?: $b', (string) $assignment);
	assertParsesBack($assignment);

	Assert::same('$a .= ($b or $c)', (string) $b->combinedAssign(expression('$a'), '.=', expression('$b or $c')));
	Assert::same('$a *= $b = 2', (string) $b->combinedAssign(expression('$a'), '*=', expression('$b = 2')));

	Assert::exception(fn() => $b->combinedAssign(expression('$a'), '=', expression('$b')), InvalidArgumentException::class, '`=` is not a combined assignment operator.');
	Assert::exception(fn() => $b->combinedAssign(expression('f()'), '+=', expression('$b')), InvalidArgumentException::class, '`f()` is no place to assign to.');
});


test('a refused input leaves the tree it would take a node from as it was', function () use ($b) {
	$code = "<?php\nfunction f() {\n\t/* keep */ \$x->y ;\n\tg( \$z ) ;\n}\n";
	$file = (new Parser)->parse($code);
	$live = findNode($file, PropertyFetchNode::class);
	$attempts = [
		fn() => $b->methodCall($live, 'b c'),
		fn() => $b->staticMethodCall($live, 'b c'),
		fn() => $b->binary($live, 'foo', 1),
		fn() => $b->combinedAssign($live, '=', 1),
		fn() => $b->assign(expression('f()'), $live),
		fn() => $b->arguments([$live, 'a b' => 1]),
		fn() => $b->expression('$x', x: $live, y: expression('$y')),
	];
	foreach ($attempts as $attempt) {
		assertRefused($attempt, Exception::class, null, $file);
	}

	$detached = expression('$o /* c */');
	$detached->setEdgeTrivia([], [new PhpSyntax\Trivia(PhpSyntax\Trivia::Whitespace, ' ')]);
	assertRefused(fn() => $b->methodCall($detached, 'b c'), InvalidArgumentException::class, null, $detached);
});


test('a template replaces its placeholders, in parentheses where the place asks', function () use ($b) {
	$sum = expression('$a + $b');
	$node = $b->expression('$x * 2', x: $sum);
	Assert::same('($a + $b) * 2', (string) $node);
	assertParsesBack($node);
	Assert::same('$a + $b * 2', (string) $b->expression('$a + $x', x: expression('$b * 2')));

	// a placeholder used twice gets a copy the second time
	$value = expression('$items[0]');
	$node = $b->expression('isset($v) ? $v : null', v: $value);
	Assert::same('isset($items[0]) ? $items[0] : null', (string) $node);
	assert($node instanceof PhpSyntax\Nodes\Expression\TernaryNode);
	Assert::same($node, $value->findAncestor(PhpSyntax\Nodes\Expression\IssetNode::class)?->parent);
	Assert::notSame($value, $node->then);

	// variables that are no placeholders stay, a variable inside a placeholder is never taken for one
	Assert::same('$this->set($y)', (string) $b->expression('$this->set($x)', x: expression('$y')));
	Assert::same('f($x, $y)', (string) $b->expression('f($y, $x)', y: expression('$x'), x: expression('$y')));

	// the whole template may be a placeholder
	$alone = expression('$q');
	Assert::same($alone, $b->expression('$x', x: $alone));

	$detached = expression('$a');
	$detached->setEdgeTrivia([new PhpSyntax\Trivia(PhpSyntax\Trivia::Whitespace, ' ')]);
	Assert::exception(fn() => $b->expression('$x + 1', x: $detached, y: expression('$b')), InvalidArgumentException::class, 'Placeholder `$y` does not stand in the template.');
	Assert::same(' $a', (string) $detached); // refused before it was taken
	Assert::exception(fn() => $b->expression('$x', expression('$a')), InvalidArgumentException::class, 'A placeholder is given by its name, as `name: $node` for `$name`.');
});


test('statements and fragments are templates too', function () use ($b) {
	$statement = $b->statement('return $x;', x: expression('$a ?: $b'));
	Assert::type(ReturnNode::class, $statement);
	Assert::same('return $a ?: $b;', (string) $statement);

	$statement = $b->statement('foreach ($items as $k => $v) { echo $k; }', items: expression('$this->items'), v: expression('[$a, $b]'));
	assert($statement instanceof ForeachNode);
	Assert::same('foreach ($this->items as $k => [$a, $b]) { echo $k; }', (string) $statement);

	$item = $b->fragment(ArrayItemNode::class, "'key' => \$v", v: expression('$a + 1'));
	Assert::same("'key' => \$a + 1", (string) $item);
});


test('a placeholder that cannot stand where the variable stands is refused', function () use ($b) {
	$destructuring = expression('[$a, $b] = $c');
	assert($destructuring instanceof AssignmentNode && $destructuring->target instanceof DestructuringNode);
	$target = $destructuring->target;

	Assert::same('[$a, $b] = $rows', (string) $b->expression('$t = $rows', t: $target));
	Assert::exception(
		fn() => $b->expression('f($t)', t: $target),
		InvalidArgumentException::class,
		'Placeholder `$t` is a destructuring, which stands only where a target is written.',
	);
	Assert::exception(
		fn() => $b->statement('static $x;', x: expression('$a + 1')),
		InvalidArgumentException::class,
		'Placeholder `$x` stands in the slot `variable` of `PhpSyntax\Nodes\StaticVariableNode`, which does not take `PhpSyntax\Nodes\Expression\BinaryOpNode`.',
	);
	Assert::type(ExpressionStatementNode::class, $b->statement('$x;', x: expression('$a + 1')));
	Assert::type(ArrayNode::class, $b->value([1]));
	Assert::same('f()', (string) $b->call('f'));
});


test('a placeholder refused by its place in a string leaves the detached part as it was', function () use ($b) {
	$sum = expression('$a + $b');
	$sum->setEdgeTrivia([PhpSyntax\Trivia::fromText('/* lead */')], [PhpSyntax\Trivia::fromText('/* trail */')]);
	$message = 'Expression `$a + $b` cannot be written inside a string, which takes a variable, an element, a property or a call reached from a variable.';
	assertRefused(fn() => $b->expression('"{$x}"', x: $sum), InvalidArgumentException::class, $message, $sum);

	// the second place refuses what the first one took
	assertRefused(fn() => $b->expression('f($x, "{$x}")', x: $sum), InvalidArgumentException::class, $message, $sum);
});
