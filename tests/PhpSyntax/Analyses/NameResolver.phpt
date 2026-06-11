<?php declare(strict_types=1);

use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\{Builder, Node, Parser, SymbolKind, UnqualifiedResolution};
use PhpSyntax\Nodes\{AnonymousClassNode, ConstItemNode, FileNode, NameNode};
use PhpSyntax\Nodes\Expression\{ClassConstantFetchNode, ConstantFetchNode, FunctionCallNode, NewNode, VariableNode};
use PhpSyntax\Nodes\Statement\{ClassNode, FunctionNode, NamespaceNode};
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


$code = <<<'XX'
	<?php
	namespace App\Model;

	use Foo\Bar;
	use Foo\Baz as Qux, Other\Thing;
	use function Foo\helper, Foo\other as alias;
	use const Foo\LIMIT;
	use Lib\{Alpha, Beta as Gamma, function fn1, const C1};

	new Bar; new Qux; new Thing\Sub; new Bar\Nested; new Local; new \Global\Cls; new namespace\Rel; new Alpha; new Gamma; new self; new static;
	helper(); alias(); fn1(); strlen(); local(); \strlen(); Foo\f(); Bar\f(); namespace\g();
	LIMIT; C1; PHP_EOL; LOCAL; \E_ALL; Bar\X; Qux::CONST;
	function local() {}

	namespace {
		use Foo\Bar;
		new Bar; new Baz; strlen(); other();
	}
	XX;

$file = (new Parser)->parse($code);
$resolver = new NameResolver($file);


/** @return list<string> */
function classes(FileNode $file, NameResolver $resolver): array
{
	return array_map(
		fn(NewNode $new) => $new->class instanceof NameNode ? $resolver->resolveClass($new->class) : '?',
		$file->find(NewNode::class),
	);
}


test('namespaces', function () use ($file, $resolver) {
	[$model, $global] = $file->statements->getItems();
	Assert::same('App\Model', $resolver->getNamespace($model));
	Assert::same('App\Model', $resolver->getNamespace($file->find(NewNode::class)[0]));
	Assert::same('', $resolver->getNamespace($global));
	Assert::same('', $resolver->getNamespace($file->find(NewNode::class)[11]));

	// the head of a namespace belongs to it, its name and its keyword alike
	assert($model instanceof NamespaceNode && $model->name !== null);
	Assert::same('App\Model', $resolver->getNamespace($model->name));
	Assert::same('App\Model', $resolver->getNamespace($model->namespaceKeyword));
});


test('classes: imports, aliases, group use, qualified prefixes, special names', function () use ($file, $resolver) {
	Assert::same([
		'Foo\Bar', 'Foo\Baz', 'Other\Thing\Sub', 'Foo\Bar\Nested', 'App\Model\Local', 'Global\Cls', 'App\Model\Rel', 'Lib\Alpha', 'Lib\Beta', 'self', 'static',
		'Foo\Bar', 'Baz',
	], classes($file, $resolver));
});


test('imports of each kind in effect at a node', function () use ($file, $resolver) {
	$new = $file->findFirst(NewNode::class) ?? throw new LogicException;
	Assert::same(
		['bar' => 'Foo\Bar', 'qux' => 'Foo\Baz', 'thing' => 'Other\Thing', 'alpha' => 'Lib\Alpha', 'gamma' => 'Lib\Beta'],
		$resolver->getImports(SymbolKind::ClassLike, $new),
	);
	Assert::same(['helper' => 'Foo\helper', 'alias' => 'Foo\other', 'fn1' => 'Lib\fn1'], $resolver->getImports(SymbolKind::Function, $new));
	Assert::same(['LIMIT' => 'Foo\LIMIT', 'C1' => 'Lib\C1'], $resolver->getImports(SymbolKind::Constant, $new));
});


test('functions: imports, fallback to global, declared in the namespace', function () use ($file, $resolver) {
	$calls = $file->find(FunctionCallNode::class);
	$resolved = array_map(fn(FunctionCallNode $call) => $call->name instanceof NameNode ? $resolver->resolveFunction($call->name) : '?', $calls);
	Assert::same([
		'Foo\helper', 'Foo\other', 'Lib\fn1', 'strlen', 'App\Model\local', 'strlen', 'App\Model\Foo\f', 'Foo\Bar\f', 'App\Model\g',
		'strlen', 'other',
	], $resolved);

	Assert::same([false, false, false, true, false, true, false, false, false, true, true], array_map(fn(Node $call) => $resolver->isGlobalFunctionCall($call), $calls));
	Assert::true($resolver->isGlobalFunctionCall($calls[3], 'STRLEN'));
	Assert::false($resolver->isGlobalFunctionCall($calls[3], 'strtolower'));
	Assert::false($resolver->isGlobalFunctionCall($file->statements, 'strlen'));
});


test('constants: case-sensitive imports and fallback', function () use ($file, $resolver) {
	$constants = array_map(
		fn(ConstantFetchNode $fetch) => $resolver->resolveConstant($fetch->name),
		$file->find(ConstantFetchNode::class),
	);
	Assert::same(['Foo\LIMIT', 'Lib\C1', 'PHP_EOL', 'LOCAL', 'E_ALL', 'Foo\Bar\X'], $constants);
	$fetch = $file->find(ClassConstantFetchNode::class)[0];
	Assert::type(NameNode::class, $fetch->class);
	Assert::same('Foo\Baz', $resolver->resolveClass($fetch->class));
});


test('constants: declared in the namespace', function () {
	$code = <<<'CODE'
		<?php
		namespace App;

		const MAX = 1, Min = 0;

		MAX; Min; min; PHP_EOL;

		namespace {
			MAX;
		}
		CODE;
	$file = (new Parser)->parse($code);
	$resolver = new NameResolver($file);
	$fetches = $file->find(ConstantFetchNode::class);

	Assert::same(
		['App\MAX', 'App\Min', 'min', 'PHP_EOL', 'MAX'],
		array_map(fn(ConstantFetchNode $fetch) => $resolver->resolveConstant($fetch->name), $fetches),
	);
	Assert::false($resolver->isAliasFree('MAX', SymbolKind::Constant, $fetches[0]));
	Assert::true($resolver->isAliasFree('max', SymbolKind::Constant, $fetches[0]));
	Assert::true($resolver->isAliasFree('MAX', SymbolKind::Function, $fetches[0]));
	Assert::true($resolver->isAliasFree('MAX', SymbolKind::Constant, $fetches[4]));
});


test('a declaration reaches every block of its namespace', function () {
	$code = <<<'CODE'
		<?php
		namespace A {
			function count($x) {}
			const LIMIT = 1;
		}
		namespace A {
			count([]); strlen('');
			LIMIT; PHP_EOL;
		}
		namespace B {
			count([]);
			LIMIT;
		}
		CODE;
	$file = (new Parser)->parse($code);
	$resolver = new NameResolver($file);

	$calls = $file->find(FunctionCallNode::class);
	Assert::same(
		['A\count', 'strlen', 'count'],
		array_map(fn(FunctionCallNode $call) => $call->name instanceof NameNode ? $resolver->resolveFunction($call->name) : '?', $calls),
	);
	Assert::same([false, true, true], array_map(fn(Node $call) => $resolver->isGlobalFunctionCall($call), $calls));
	Assert::same(
		['A\LIMIT', 'PHP_EOL', 'LIMIT'],
		array_map(fn(ConstantFetchNode $fetch) => $resolver->resolveConstant($fetch->name), $file->find(ConstantFetchNode::class)),
	);
});


test('a declaration inside a condition or a function body is a declaration of the namespace', function () {
	$code = <<<'CODE'
		<?php
		namespace App;

		local(); inner();

		if (!function_exists('App\local')) {
			function local() {}
		}

		function outer()
		{
			function inner() {}
		}
		CODE;
	$file = (new Parser)->parse($code);
	$resolver = new NameResolver($file);

	$calls = $file->find(FunctionCallNode::class);
	Assert::same(
		['App\local', 'App\inner', 'function_exists'],
		array_map(fn(FunctionCallNode $call) => $call->name instanceof NameNode ? $resolver->resolveFunction($call->name) : '?', $calls),
	);
	Assert::same([false, false, true], array_map(fn(Node $call) => $resolver->isGlobalFunctionCall($call), $calls));
	Assert::false($resolver->isAliasFree('local', SymbolKind::Function, $calls[0]));
});


test('the functions and constants a node declares into a namespace', function () {
	$code = <<<'CODE'
		<?php
		namespace App;

		const A = 1, B = 2;
		define('App\C', 3);
		define('\App\Unreachable', 4);
		define('GLOBAL_ONE', 5);

		if (true) {
			function local() {}
		}
		CODE;
	$file = (new Parser)->parse($code);
	$found = new NameResolver($file)->findNamespacedDeclarations($file);
	Assert::same(
		[['Constant', 'App\A'], ['Constant', 'App\B'], ['Constant', 'App\C'], ['Function', 'App\local']],
		array_map(fn(PhpSyntax\Analyses\Declaration $declaration) => [$declaration->kind->name, $declaration->name], $found),
	);
	Assert::same("'App\\C'", $found[2]->node->text); // the string define() is given names the constant
});


test('the shortest way to write a name where the node stands', function () {
	$code = <<<'CODE'
		<?php
		namespace App\Model;

		use Nette\Utils\Strings;
		use Nette\Utils as U;
		use function Nette\Utils\first;

		$x;

		class Entity {}

		function build() {}
		CODE;
	$file = (new Parser)->parse($code);
	$resolver = new NameResolver($file);
	$at = $file->find(VariableNode::class)[0];

	Assert::same('Strings', $resolver->shortenName('Nette\Utils\Strings', SymbolKind::ClassLike, $at)); // dresscode:ignore literals.classNameInString
	Assert::same('Strings', $resolver->shortenName('\Nette\Utils\Strings', SymbolKind::ClassLike, $at)); // dresscode:ignore literals.classNameInString
	Assert::same('U\Json', $resolver->shortenName('Nette\Utils\Json', SymbolKind::ClassLike, $at)); // dresscode:ignore literals.classNameInString
	Assert::same('Entity', $resolver->shortenName('App\Model\Entity', SymbolKind::ClassLike, $at)); // the file declares it
	Assert::same('\App\Model\Strings', $resolver->shortenName('App\Model\Strings', SymbolKind::ClassLike, $at)); // the import takes the name
	Assert::same('Sub\Thing', $resolver->shortenName('App\Model\Sub\Thing', SymbolKind::ClassLike, $at));
	Assert::same('\Other\Thing', $resolver->shortenName('Other\Thing', SymbolKind::ClassLike, $at));
	Assert::same('first', $resolver->shortenName('Nette\Utils\first', SymbolKind::Function, $at));
	Assert::same('\strlen', $resolver->shortenName('strlen', SymbolKind::Function, $at));
	Assert::same('\PHP_EOL', $resolver->shortenName('PHP_EOL', SymbolKind::Constant, $at));
	Assert::same('build', $resolver->shortenName('App\Model\build', SymbolKind::Function, $at)); // the file declares it
	Assert::same('\App\Model\missing', $resolver->shortenName('App\Model\missing', SymbolKind::Function, $at));
	// a qualified function or constant reads its first part through the imports of classes, as a class does
	Assert::same('U\run', $resolver->shortenName('Nette\Utils\run', SymbolKind::Function, $at));
	Assert::same('U\VERSION', $resolver->shortenName('Nette\Utils\VERSION', SymbolKind::Constant, $at));
	Assert::same('Sub\run', $resolver->shortenName('App\Model\Sub\run', SymbolKind::Function, $at));
	Assert::same('\App\Model\Strings\run', $resolver->shortenName('App\Model\Strings\run', SymbolKind::Function, $at)); // the import takes the first part

	Assert::false($resolver->isAliasFree('Strings', SymbolKind::ClassLike, $at));
	Assert::false($resolver->isAliasFree('strings', SymbolKind::ClassLike, $at));
	Assert::false($resolver->isAliasFree('Entity', SymbolKind::ClassLike, $at));
	Assert::true($resolver->isAliasFree('Json', SymbolKind::ClassLike, $at));
	Assert::false($resolver->isAliasFree('first', SymbolKind::Function, $at));
	Assert::true($resolver->isAliasFree('Strings', SymbolKind::Function, $at));
});


test('of two imports of one class the shorter alias is written, whichever comes first', function () {
	foreach (["use Shop\\Money;\nuse Shop\\Money as Cash;", "use Shop\\Money as Cash;\nuse Shop\\Money;"] as $imports) {
		$file = (new Parser)->parse("<?php\nnamespace App;\n$imports\n\$x;\n");
		$at = $file->find(VariableNode::class)[0];
		Assert::same('Cash', new NameResolver($file)->shortenName('Shop\Money', SymbolKind::ClassLike, $at));
	}

	$file = (new Parser)->parse("<?php\nnamespace App;\nuse Shop\\Money as Cash;\nuse Shop\\Money as Coin;\n\$x;\n");
	$at = $file->find(VariableNode::class)[0];
	Assert::same('Cash', new NameResolver($file)->shortenName('Shop\Money', SymbolKind::ClassLike, $at)); // of equal ones, the first
});


test('a class the global namespace declares is written bare, one an import takes the name of is not', function () {
	$file = (new Parser)->parse('<?php use Foo\Bar; class Entity {} $x;');
	$resolver = new NameResolver($file);
	$at = $file->find(VariableNode::class)[0];
	Assert::same('Entity', $resolver->shortenName('Entity', SymbolKind::ClassLike, $at));
	Assert::same('\Bar', $resolver->shortenName('Bar', SymbolKind::ClassLike, $at));
});


test('in the global namespace a name needs no backslash', function () {
	$file = (new Parser)->parse('<?php use Foo\Bar; $x;');
	$resolver = new NameResolver($file);
	$at = $file->find(VariableNode::class)[0];

	Assert::same('Bar', $resolver->shortenName('Foo\Bar', SymbolKind::ClassLike, $at));
	Assert::same('Other\Cls', $resolver->shortenName('Other\Cls', SymbolKind::ClassLike, $at));
	Assert::same('strlen', $resolver->shortenName('strlen', SymbolKind::Function, $at));
	Assert::same('PHP_EOL', $resolver->shortenName('PHP_EOL', SymbolKind::Constant, $at));
	// the import takes the first segment, so a name starting with it has to stay fully qualified
	Assert::same('\Bar\Sub', $resolver->shortenName('Bar\Sub', SymbolKind::ClassLike, $at));
});


test('a name an import has taken over is written with the backslash', function () {
	$file = (new Parser)->parse('<?php use function X\count; use const X\FOO; use Y\Cls; $x;');
	$resolver = new NameResolver($file);
	$at = $file->find(VariableNode::class)[0];

	// the bare name would be the imported symbol, so the global one keeps its backslash
	Assert::same('\count', $resolver->shortenName('count', SymbolKind::Function, $at));
	Assert::same('\FOO', $resolver->shortenName('FOO', SymbolKind::Constant, $at));
	Assert::same('\Cls', $resolver->shortenName('Cls', SymbolKind::ClassLike, $at));
	Assert::same('count', $resolver->shortenName('X\count', SymbolKind::Function, $at));
	Assert::same('foo', $resolver->shortenName('foo', SymbolKind::Constant, $at)); // constants are case-sensitive

	// whatever it writes, reading it back where it stands gives the symbol it was asked about
	$readBack = function (string $fullName, SymbolKind $kind) use ($resolver, $at): string {
		$name = (new Builder)->name($resolver->shortenName($fullName, $kind, $at));
		return match ($kind) {
			SymbolKind::Function => $resolver->resolveFunction($name, $at),
			SymbolKind::Constant => $resolver->resolveConstant($name, $at),
			SymbolKind::ClassLike => $resolver->resolveClass($name, $at),
		};
	};
	foreach ([
		['count', SymbolKind::Function], ['X\count', SymbolKind::Function], ['strlen', SymbolKind::Function],
		['FOO', SymbolKind::Constant], ['X\FOO', SymbolKind::Constant], ['foo', SymbolKind::Constant],
		['Cls', SymbolKind::ClassLike], ['Y\Cls', SymbolKind::ClassLike], ['Other\Thing', SymbolKind::ClassLike],
	] as [$fullName, $kind]) {
		Assert::same($fullName, $readBack($fullName, $kind), $fullName);
	}
});

test('a name outside the file is refused, unless it is told where to read it', function () {
	$file = (new Parser)->parse("<?php\nnamespace Abc;\nuse Foo\\Bar;\nfunction f() { new Bar; }\n");
	$resolver = new NameResolver($file);
	$function = $file->find(FunctionNode::class)[0];
	$copy = clone $function;
	$name = $copy->findFirst(NameNode::class);
	Assert::type(NameNode::class, $name);

	// answering in the global namespace would be an answer to a question nobody asked
	Assert::exception(
		fn() => $resolver->resolveClass($name),
		LogicException::class,
		'The node does not belong to the file of the analysis.',
	);
	Assert::same('Foo\Bar', $resolver->resolveClass($name, $function));
	Assert::same('Abc', $resolver->getNamespace($function));
});


test('the name a declaration introduces, and the declaration of a name', function () {
	$file = (new Parser)->parse(<<<'XX'
		<?php
		namespace App\Shop;
		class Cart { const LIMIT = 5; }
		interface Sellable {}
		function total() {}
		function cart() {}
		const LIMIT = 10;
		$x = new class {};
		XX);
	$resolver = new NameResolver($file);

	Assert::same('App\Shop\Cart', $resolver->getDeclaredName($file->find(ClassNode::class)[0]));
	Assert::same('App\Shop\total', $resolver->getDeclaredName($file->find(FunctionNode::class)[0]));
	[$classConstant, $constant] = $file->find(ConstItemNode::class);
	Assert::same('App\Shop\LIMIT', $resolver->getDeclaredName($constant));
	Assert::null($resolver->getDeclaredName($classConstant)); // it introduces nothing into the namespace
	Assert::null($resolver->getDeclaredName($file->find(AnonymousClassNode::class)[0]));
	Assert::null($resolver->getDeclaredName($file->find(VariableNode::class)[0]));

	// a class, a function and a constant of one name are looked up apart, and a leading backslash is no obstacle
	Assert::same('Cart', $resolver->findDeclaration('App\Shop\Cart', SymbolKind::ClassLike)?->name?->text);
	Assert::same('cart', $resolver->findDeclaration('App\Shop\Cart', SymbolKind::Function)?->name->text);
	Assert::same('Sellable', $resolver->findDeclaration('\app\shop\sellable', SymbolKind::ClassLike)?->name?->text);
	Assert::null($resolver->findDeclaration('App\Shop\total', SymbolKind::ClassLike));
	Assert::null($resolver->findDeclaration('App\Shop\Missing', SymbolKind::Function));

	// the letter case counts the way PHP reads it: in the name of a constant, never in its namespace
	Assert::same($constant, $resolver->findDeclaration('\app\shop\LIMIT', SymbolKind::Constant));
	Assert::null($resolver->findDeclaration('App\Shop\limit', SymbolKind::Constant));
});


test('findDeclaration() gives the first of several declarations of one name, a nested one counting too', function () {
	$file = (new Parser)->parse(<<<'XX'
		<?php
		namespace App;
		if (!function_exists('App\helper')) {
			function helper() {}
		}
		function helper() {}
		namespace Other;
		function helper() {}
		XX);
	$resolver = new NameResolver($file);
	[$nested, $second, $other] = $file->find(FunctionNode::class);

	Assert::same($nested, $resolver->findDeclaration('App\helper', SymbolKind::Function));
	Assert::same($other, $resolver->findDeclaration('other\HELPER', SymbolKind::Function));
	Assert::null($resolver->findDeclaration('helper', SymbolKind::Function));
});


test('a name that refers to no symbol is refused, not read as one of the namespace', function () {
	$file = (new Parser)->parse("<?php\nnamespace App;\nuse function Lib\\helper;\nfunction f(int \$a, self \$b, Bar \$c) {}\n");
	$resolver = new NameResolver($file);
	[$namespace, $import, $int, $self, $bar] = $file->find(NameNode::class);

	Assert::same('App\Bar', $resolver->resolveClass($bar));
	Assert::same('self', $resolver->resolveClass($self));
	Assert::exception(
		fn() => $resolver->resolveClass($int),
		InvalidArgumentException::class,
		'`int` refers to no symbol: it declares one, names a builtin type, or is `self`, `static` or `parent`.',
	);
	Assert::exception(fn() => $resolver->resolveClass($namespace), InvalidArgumentException::class, '`App` refers to no symbol: %a%');
	Assert::exception(fn() => $resolver->resolveFunction($import), InvalidArgumentException::class, '`Lib\\helper` refers to no symbol: %a%');
});


test('the resolver reads the tree anew once it has changed', function () {
	$file = (new Parser)->parse("<?php\nnamespace App;\nuse A\\Foo;\nnew Foo;\nfunction f() {}\n");
	$resolver = new NameResolver($file);
	$new = $file->findFirst(NewNode::class) ?? throw new LogicException;
	assert($new->class instanceof NameNode);
	Assert::same('A\Foo', $resolver->resolveClass($new->class));
	Assert::type(FunctionNode::class, $resolver->findDeclaration('App\f', SymbolKind::Function));

	$import = $file->findFirst(PhpSyntax\Nodes\UseItemNode::class) ?? throw new LogicException;
	$import->name->text = 'B\Foo';
	$function = $file->findFirst(FunctionNode::class) ?? throw new LogicException;
	$function->name->text = 'g';
	Assert::same('B\Foo', $resolver->resolveClass($new->class));
	Assert::null($resolver->findDeclaration('App\f', SymbolKind::Function));
	Assert::type(FunctionNode::class, $resolver->findDeclaration('App\g', SymbolKind::Function));
});


test('a function declared under a condition makes the call uncertain, PHP declaring it only when the code runs', function () {
	$file = (new Parser)->parse("<?php\nnamespace App;\nif (false) {\n\tfunction strlen(\$x) {}\n}\nstrlen('abc');\nfunction local() {}\nlocal();\n");
	$resolver = new NameResolver($file);
	[$strlen, $local] = $file->find(FunctionCallNode::class, fn($call) => $call->name instanceof NameNode && !$call->name->equals('function_exists'));
	Assert::same(UnqualifiedResolution::Uncertain, $resolver->getUnqualifiedResolution('strlen', SymbolKind::Function, $strlen));
	Assert::same(UnqualifiedResolution::Namespaced, $resolver->getUnqualifiedResolution('local', SymbolKind::Function, $local));
	Assert::false($resolver->isAliasFree('strlen', SymbolKind::Function, $strlen)); // the name is taken all the same
	Assert::same('\App\strlen', $resolver->shortenName('App\strlen', SymbolKind::Function, $strlen)); // the bare name may reach the global one
	Assert::same('local', $resolver->shortenName('App\local', SymbolKind::Function, $local));
});
