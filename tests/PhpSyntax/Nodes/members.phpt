<?php declare(strict_types=1);

/**
 * What the modifiers, the parameters, the arguments, the types and the other constructs say about themselves.
 */

use PhpSyntax\Node;
use PhpSyntax\Nodes\ArgumentListNode;
use PhpSyntax\Nodes\Expression\CastNode;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Expression\MethodCallNode;
use PhpSyntax\Nodes\Expression\PropertyFetchNode;
use PhpSyntax\Nodes\IdentifierNode;
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Member\PropertyNode;
use PhpSyntax\Nodes\ParameterNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\Statement\UseNode;
use PhpSyntax\Nodes\Type\NamedTypeNode;
use PhpSyntax\Nodes\TypeNode;
use PhpSyntax\Nodes\UseItemNode;
use PhpSyntax\Parser;
use PhpSyntax\SymbolKind;
use PhpSyntax\Visibility;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


function parseFile(string $code): PhpSyntax\Nodes\FileNode
{
	return (new Parser)->parse("<?php\n$code");
}


test('the visibility and the other modifiers', function () {
	$file = parseFile('class A { public $a; protected static $b; private final function c(int $f) {} readonly public(set) $d; var $e; }');
	$members = [];
	foreach ($file->find(PropertyNode::class) as $property) {
		$members[] = $property->modifiers;
	}

	foreach ($file->find(MethodNode::class) as $method) {
		$members[] = $method->modifiers;
	}

	[$a, $b, $d, $e, $c] = $members;
	$f = $file->find(ParameterNode::class)[0]->modifiers; // a parameter that promotes nothing has none
	Assert::same(Visibility::Public, $a->visibility);
	Assert::true($a->isPublic());
	Assert::same(Visibility::Protected, $b->visibility);
	Assert::true($b->isProtected());
	Assert::true($b->isStatic());
	Assert::same(Visibility::Private, $c->visibility);
	Assert::true($c->isPrivate());
	Assert::true($c->isFinal());
	Assert::false($c->isAbstract());
	Assert::true($d->isReadonly());
	Assert::same(Visibility::Public, $d->writeVisibility);
	Assert::same(Visibility::Public, $e->visibility); // var is public
	Assert::null($f->visibility); // no modifier at all, which is public too
	Assert::true($f->isPublic());
	Assert::null($f->writeVisibility);
	Assert::same('static', $b->findToken(PhpSyntax\TokenKind::Static)?->text);
	Assert::null($b->findToken(PhpSyntax\TokenKind::Final));
});


test('an identifier takes an identifier and nothing else', function () {
	$file = parseFile('class A { function b() {} }');
	$method = $file->find(MethodNode::class)[0];
	$method->name->text = 'c';
	Assert::same('c', $method->name->text);
	Assert::exception(fn() => $method->name->text = 'c d', InvalidArgumentException::class, "'c d' is not an identifier.");
	Assert::exception(fn() => $method->name->text = '', InvalidArgumentException::class, "'' is not an identifier.");
	Assert::same('c', $method->name->text);

	Assert::same('d', IdentifierNode::fromText('d')->text);
	Assert::exception(fn() => IdentifierNode::fromText('d e'), InvalidArgumentException::class, "'d e' is not an identifier.");
});


test('a promoted parameter is the one with modifiers', function () {
	$file = parseFile('class A { function __construct(private int $a, int $b) {} }');
	[$a, $b] = $file->find(ParameterNode::class);
	Assert::true($a->isPromoted());
	Assert::same(Visibility::Private, $a->modifiers->visibility);
	Assert::false($b->isPromoted());
});


test('the argument a parameter gets, and the partial application', function () {
	$file = parseFile('f(1, b: 2, ...$c); g(...); h(1, ?, c: ?); i(...$a); j(...$a, b: 2); k(...$a, c: 3);');
	[$call, $callable, $partial, $unpacked, $named, $other] = $file->find(ArgumentListNode::class);
	Assert::false($call->isPartialApplication());
	Assert::same('1', (string) $call->findArgument('a', 0)?->value);
	Assert::same('2', (string) $call->findArgument('b', 1)?->value);
	Assert::same('2', (string) $call->findArgument('b', 9)?->value); // the name wins over the position
	Assert::null($call->findArgument('z', 9)); // what the unpacked array holds could be it

	Assert::null($unpacked->findArgument('a', 0)); // it stands where the array unpacks

	// an unpacked array takes the answer from the position after it, not from a name written there
	Assert::same('2', (string) $named->findArgument('b', 0)?->value);
	Assert::null($other->findArgument('b', 0));

	Assert::true($callable->isPartialApplication());
	Assert::null($callable->findArgument('a', 0));

	Assert::true($partial->isPartialApplication());
	Assert::same('1', (string) $partial->findArgument('a', 0)?->value);
	Assert::null($partial->findArgument('b', 1)); // the placeholder holds the position
	Assert::null($partial->findArgument('c', 9));
});


test('a nullsafe call and fetch', function () {
	$file = parseFile('$a?->b(); $c->d(); $e?->f; $g->h;');
	[$nullsafeCall, $call] = $file->find(MethodCallNode::class);
	Assert::true($nullsafeCall->isNullsafe());
	Assert::false($call->isNullsafe());
	[$nullsafeFetch, $fetch] = $file->find(PropertyFetchNode::class);
	Assert::true($nullsafeFetch->isNullsafe());
	Assert::false($fetch->isNullsafe());
});


test('a builtin type and a type that accepts null', function () {
	$file = parseFile('function f(int $a, ?Foo $b, self $c, Foo|null $d, mixed $e, Foo $f, Foo&Bar $g) {}');
	$types = array_map(fn(ParameterNode $param) => $param->type, $file->find(ParameterNode::class));
	Assert::same([true, false, true, false, true, false, false], array_map(
		fn(?TypeNode $type) => $type instanceof NamedTypeNode && $type->isBuiltin(),
		$types,
	));
	Assert::same([false, true, false, true, true, false, false], array_map(
		fn(?TypeNode $type) => $type?->allowsNull() ?? false,
		$types,
	));
});


test('the constructor by its name, whatever its letter case', function () {
	$file = parseFile('class A { function __CONSTRUCT() {} function b() {} }');
	Assert::same([true, false], array_map(fn(MethodNode $m) => $m->isConstructor(), $file->find(MethodNode::class)));
});


test('the type of a cast in the name PHP knows it by', function () {
	$file = parseFile('(int) $a; (integer) $b; (boolean) $c; ( double ) $d; (real) $e; (binary) $f; (object) $g;');
	Assert::same(
		['int', 'int', 'bool', 'float', 'float', 'string', 'object'],
		array_map(fn(CastNode $cast) => $cast->type, $file->find(CastNode::class)),
	);
});


test('a list is iterated and counted like a list, its items without the separators', function () {
	$file = parseFile('f(1, 2, 3); class A { public $x; public $y; }');
	$call = $file->find(FunctionCallNode::class)[0];
	Assert::same(['1', '2', '3'], array_map(fn(Node $arg) => $arg->text, iterator_to_array($call->arguments->items)));
	Assert::count(3, $call->arguments->items);

	$class = $file->find(ClassNode::class)[0];
	Assert::count(2, $class->members);

	$property = $file->find(PropertyNode::class)[0];
	Assert::count(1, $property->modifiers);
	Assert::same(['public'], array_map(fn($token) => $token->text, iterator_to_array($property->modifiers)));
});


test('what a use statement and its items import', function () {
	$file = parseFile('use A\B; use function C\d; use const E\F; use G\{H, function i, const J}; use \K\L as M;');
	$items = $file->find(UseItemNode::class);
	Assert::same(
		[SymbolKind::ClassLike, SymbolKind::Function, SymbolKind::Constant, SymbolKind::ClassLike, SymbolKind::Function, SymbolKind::Constant, SymbolKind::ClassLike],
		array_map(fn(UseItemNode $item) => $item->kind, $items),
	);

	// the statement says what an item without a type of its own imports
	Assert::same(
		[SymbolKind::ClassLike, SymbolKind::Function, SymbolKind::Constant, SymbolKind::ClassLike, SymbolKind::ClassLike],
		array_map(fn(UseNode $stmt) => $stmt->kind, $file->find(UseNode::class)),
	);

	// the prefix of a group belongs to the name every item of it imports, and no leading backslash does
	Assert::same(
		['A\B', 'C\d', 'E\F', 'G\H', 'G\i', 'G\J', 'K\L'],
		array_map(fn(UseItemNode $item) => $item->fullName, $items),
	);
	Assert::same(
		[false, false, false, true, true, true, false],
		array_map(fn(UseItemNode $item) => $item->getStatement()?->isGroup(), $items),
	);

	// an item outside a statement, as a fragment is, knows only what is written in it
	$item = (new Parser)->parseFragment(UseItemNode::class, 'A\B as C');
	Assert::null($item->getStatement());
	Assert::same('A\B', $item->fullName);
	Assert::same(SymbolKind::ClassLike, $item->kind);
});


test('an import is added the way the statement writes its items', function () {
	$add = function (string $code, string $name, ?string $alias = null, ?int $index = null): string {
		$file = parseFile($code);
		$file->getIndex()->getTokens(); // the index is there, so the write has to keep it right
		$file->find(UseNode::class)[0]->addImport($name, $alias, $index);
		return substr((string) $file, strlen("<?php\n"));
	};

	// a plain import takes the whole name, without a leading backslash of its own
	Assert::same('use App\Money, App\Order;', $add('use App\Money;', 'App\Order'));
	Assert::same('use App\Money, Shop\Invoice as Doc;', $add('use App\Money;', '\Shop\Invoice', 'Doc'));

	// a group writes the name under its prefix, and refuses one standing outside it
	Assert::same('use Shop\{Invoice, App\Money};', $add('use Shop\{Invoice};', 'Shop\App\Money'));
	Assert::exception(
		fn() => $add('use Shop\{Invoice};', 'App\Money'),
		InvalidArgumentException::class,
		"The name 'App\\Money' does not stand under the prefix of the group.",
	);

	// the index says where among the items, and the item takes the place of its neighbor in the lines
	Assert::same('use E\F, A\B, C\D;', $add('use A\B, C\D;', 'E\F', null, 0));
	Assert::same(
		"use App\\{\n\tMoney,\n\tOrder,\n};\n",
		$add("use App\\{\n\tMoney,\n};\n", 'App\Order'),
	);

	// the item imports what the statement imports, and the name it stands for is the one given
	$file = parseFile('use function A\b;');
	$item = $file->find(UseNode::class)[0]->addImport('A\c');
	Assert::same(SymbolKind::Function, $item->kind);
	Assert::same('A\c', $item->fullName);
	Assert::same("<?php\nuse function A\\b, A\\c;", (string) $file);

	// neither the name nor the alias may be anything but the one token it is written as
	Assert::exception(fn() => $add('use A\B;', 'C\D as E'), InvalidArgumentException::class, "'C\\D as E' is not a name.");
	Assert::exception(fn() => $add('use A\B;', 'C\D', 'e f'), InvalidArgumentException::class, "'e f' is not an identifier.");
});
