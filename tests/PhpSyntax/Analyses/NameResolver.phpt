<?php declare(strict_types=1);

use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\Node;
use PhpSyntax\Nodes\AnonymousClassNode;
use PhpSyntax\Nodes\ConstItemNode;
use PhpSyntax\Nodes\Expression\ClassConstantFetchNode;
use PhpSyntax\Nodes\Expression\ConstantFetchNode;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Expression\NewNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\Statement\FunctionNode;
use PhpSyntax\Parser;
use PhpSyntax\SymbolKind;
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
});


test('classes: imports, aliases, group use, qualified prefixes, special names', function () use ($file, $resolver) {
	Assert::same([
		'Foo\Bar', 'Foo\Baz', 'Other\Thing\Sub', 'Foo\Bar\Nested', 'App\Model\Local', 'Global\Cls', 'App\Model\Rel', 'Lib\Alpha', 'Lib\Beta', 'self', 'static',
		'Foo\Bar', 'Baz',
	], classes($file, $resolver));
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


test('the shortest way to write a name where the node stands', function () {
	$code = <<<'CODE'
		<?php
		namespace App\Model;

		use Nette\Utils\Strings;
		use Nette\Utils as U;
		use function Nette\Utils\first;

		$x;

		class Entity {}
		CODE;
	$file = (new Parser)->parse($code);
	$resolver = new NameResolver($file);
	$at = $file->find(VariableNode::class)[0];

	Assert::same('Strings', $resolver->getShortName('Nette\Utils\Strings', SymbolKind::ClassLike, $at));
	Assert::same('Strings', $resolver->getShortName('\Nette\Utils\Strings', SymbolKind::ClassLike, $at));
	Assert::same('U\Json', $resolver->getShortName('Nette\Utils\Json', SymbolKind::ClassLike, $at));
	Assert::same('\App\Model\Entity', $resolver->getShortName('App\Model\Entity', SymbolKind::ClassLike, $at)); // the file declares Entity itself
	Assert::same('Sub\Thing', $resolver->getShortName('App\Model\Sub\Thing', SymbolKind::ClassLike, $at));
	Assert::same('\Other\Thing', $resolver->getShortName('Other\Thing', SymbolKind::ClassLike, $at));
	Assert::same('first', $resolver->getShortName('Nette\Utils\first', SymbolKind::Function, $at));
	Assert::same('\strlen', $resolver->getShortName('strlen', SymbolKind::Function, $at));
	Assert::same('\PHP_EOL', $resolver->getShortName('PHP_EOL', SymbolKind::Constant, $at));

	Assert::false($resolver->isAliasFree('Strings', SymbolKind::ClassLike, $at));
	Assert::false($resolver->isAliasFree('strings', SymbolKind::ClassLike, $at));
	Assert::false($resolver->isAliasFree('Entity', SymbolKind::ClassLike, $at));
	Assert::true($resolver->isAliasFree('Json', SymbolKind::ClassLike, $at));
	Assert::false($resolver->isAliasFree('first', SymbolKind::Function, $at));
	Assert::true($resolver->isAliasFree('Strings', SymbolKind::Function, $at));
});


test('in the global namespace a name needs no backslash', function () {
	$file = (new Parser)->parse('<?php use Foo\Bar; $x;');
	$resolver = new NameResolver($file);
	$at = $file->find(VariableNode::class)[0];

	Assert::same('Bar', $resolver->getShortName('Foo\Bar', SymbolKind::ClassLike, $at));
	Assert::same('Other\Cls', $resolver->getShortName('Other\Cls', SymbolKind::ClassLike, $at));
	Assert::same('strlen', $resolver->getShortName('strlen', SymbolKind::Function, $at));
	Assert::same('PHP_EOL', $resolver->getShortName('PHP_EOL', SymbolKind::Constant, $at));
	// the import takes the first segment, so a name starting with it has to stay fully qualified
	Assert::same('\Bar\Sub', $resolver->getShortName('Bar\Sub', SymbolKind::ClassLike, $at));
});


test('a name an import has taken over is written with the backslash', function () {
	$file = (new Parser)->parse('<?php use function X\count; use const X\FOO; use Y\Cls; $x;');
	$resolver = new NameResolver($file);
	$at = $file->find(VariableNode::class)[0];

	// the bare name would be the imported symbol, so the global one keeps its backslash
	Assert::same('\count', $resolver->getShortName('count', SymbolKind::Function, $at));
	Assert::same('\FOO', $resolver->getShortName('FOO', SymbolKind::Constant, $at));
	Assert::same('\Cls', $resolver->getShortName('Cls', SymbolKind::ClassLike, $at));
	Assert::same('count', $resolver->getShortName('X\count', SymbolKind::Function, $at));
	Assert::same('foo', $resolver->getShortName('foo', SymbolKind::Constant, $at)); // constants are case-sensitive

	// whatever it writes, reading it back where it stands gives the symbol it was asked about
	$readBack = function (string $fullName, SymbolKind $kind) use ($resolver, $at): string {
		$name = (new Parser)->parseName($resolver->getShortName($fullName, $kind, $at));
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
