<?php declare(strict_types=1);

/**
 * What the namespaces declare outside the file, given to the resolver: a listed function or constant is what an
 * unqualified name reaches, a complete list makes every other such name global for certain, and without one the
 * resolver takes the name as global and says it is uncertain.
 */

use PhpSyntax\Analyses\{NameResolver, NamespacedSymbols};
use PhpSyntax\Nodes\Expression\{ConstantFetchNode, FunctionCallNode};
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\{Parser, SymbolKind, UnqualifiedResolution};
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


$code = <<<'CODE'
	<?php
	namespace App;

	use function Lib\imported;
	use const Lib\IMPORTED;

	helper(); strlen(''); imported(); local(); \strlen(''); Sub\f();
	VERSION; PHP_EOL; IMPORTED; LOCAL;

	if (!function_exists('App\local')) {
		function local() {}
	}

	const LOCAL = 1;

	namespace {
		helper(); PHP_EOL;
	}
	CODE;
$file = (new Parser)->parse($code);
$calls = $file->find(FunctionCallNode::class); // helper, strlen, imported, local, \strlen, Sub\f, function_exists, helper
$fetches = $file->find(ConstantFetchNode::class); // VERSION, PHP_EOL, IMPORTED, LOCAL, PHP_EOL
$app = $calls[0];
$global = $calls[7];


/**
 * @param  list<FunctionCallNode>  $calls
 * @return list<string>
 */
function describeCalls(NameResolver $resolver, array $calls): array
{
	return array_map(
		fn(FunctionCallNode $call) => $call->name instanceof NameNode
			? $resolver->resolveFunction($call->name) . markUncertain($resolver, $call->name, SymbolKind::Function)
			: '',
		$calls,
	);
}


/**
 * @param  list<ConstantFetchNode>  $fetches
 * @return list<string>
 */
function describeFetches(NameResolver $resolver, array $fetches): array
{
	return array_map(
		fn(ConstantFetchNode $fetch) => $resolver->resolveConstant($fetch->name) . markUncertain($resolver, $fetch->name, SymbolKind::Constant),
		$fetches,
	);
}


function markUncertain(NameResolver $resolver, NameNode $name, SymbolKind $kind): string
{
	return $name->isUnqualified() && $resolver->getUnqualifiedResolution($name->text, $kind, $name) === UnqualifiedResolution::Uncertain ? '?' : '';
}


test('without symbols an unqualified name in a namespace is taken as global and is uncertain', function () use ($file, $calls, $fetches, $app, $global) {
	$resolver = new NameResolver($file);
	Assert::same(['helper?', 'strlen?', 'Lib\imported', 'App\local', 'strlen', 'App\Sub\f', 'function_exists?', 'helper'], describeCalls($resolver, $calls));
	Assert::same(['VERSION?', 'PHP_EOL?', 'Lib\IMPORTED', 'App\LOCAL', 'PHP_EOL'], describeFetches($resolver, $fetches));
	Assert::same(UnqualifiedResolution::Global, $resolver->getUnqualifiedResolution('true', SymbolKind::Constant, $app));
	Assert::same(UnqualifiedResolution::Global, $resolver->getUnqualifiedResolution('NULL', SymbolKind::Constant, $app));
	Assert::same(UnqualifiedResolution::Global, $resolver->getUnqualifiedResolution('helper', SymbolKind::Function, $global));
	Assert::same(UnqualifiedResolution::Namespaced, $resolver->getUnqualifiedResolution('imported', SymbolKind::Function, $app));
	Assert::same(UnqualifiedResolution::Namespaced, $resolver->getUnqualifiedResolution('LOCAL', SymbolKind::Constant, $app));
	Assert::same(UnqualifiedResolution::Namespaced, $resolver->getUnqualifiedResolution('Exception', SymbolKind::ClassLike, $app)); // a class does not fall back
	Assert::same(UnqualifiedResolution::Global, $resolver->getUnqualifiedResolution('Exception', SymbolKind::ClassLike, $global));
	Assert::exception(
		fn() => $resolver->getUnqualifiedResolution('\strlen', SymbolKind::Function, $app),
		InvalidArgumentException::class,
		"'\\strlen' is not an unqualified name.",
	);
	Assert::exception(fn() => $resolver->getUnqualifiedResolution('', SymbolKind::Function, $app), InvalidArgumentException::class, "'' is not an unqualified name.");
	Assert::exception(fn() => $resolver->getUnqualifiedResolution('a b', SymbolKind::Function, $app), InvalidArgumentException::class, "'a b' is not an unqualified name.");
	Assert::exception(
		fn() => $resolver->getUnqualifiedResolution('self', SymbolKind::ClassLike, $app),
		InvalidArgumentException::class,
		"'self' names no class of its own; it stands for one only where it is written.",
	);
});


test('an import decides what the unqualified name reaches, even one bringing in a symbol of another name', function () {
	$file = (new Parser)->parse("<?php\nnamespace App;\nuse function other as strlen;\nstrlen('');\n");
	$call = $file->find(FunctionCallNode::class)[0];
	$resolver = new NameResolver($file);
	Assert::same(UnqualifiedResolution::Global, $resolver->getUnqualifiedResolution('strlen', SymbolKind::Function, $call));
	Assert::same('\strlen', $resolver->getShortName('strlen', SymbolKind::Function, $call)); // the bare name would reach other()
});


test('a listed symbol is what the unqualified name reaches', function () use ($file, $calls, $fetches, $app) {
	$resolver = new NameResolver($file, new NamespacedSymbols(['App\helper'], ['App\VERSION']));
	Assert::same(['App\helper', 'strlen?', 'Lib\imported', 'App\local', 'strlen', 'App\Sub\f', 'function_exists?', 'helper'], describeCalls($resolver, $calls));
	Assert::same(['App\VERSION', 'PHP_EOL?', 'Lib\IMPORTED', 'App\LOCAL', 'PHP_EOL'], describeFetches($resolver, $fetches));
	Assert::false($resolver->isGlobalFunctionCall($calls[0]));
	Assert::same(UnqualifiedResolution::Namespaced, $resolver->getUnqualifiedResolution('helper', SymbolKind::Function, $app));
	Assert::false($resolver->isAliasFree('HELPER', SymbolKind::Function, $app));
	Assert::true($resolver->isAliasFree('version', SymbolKind::Constant, $app));
});


test('a complete list makes every other name global for certain', function () use ($file, $calls, $fetches) {
	$resolver = new NameResolver($file, new NamespacedSymbols(['App\helper'], complete: true));
	Assert::same(['App\helper', 'strlen', 'Lib\imported', 'App\local', 'strlen', 'App\Sub\f', 'function_exists', 'helper'], describeCalls($resolver, $calls));
	Assert::same(['VERSION', 'PHP_EOL', 'Lib\IMPORTED', 'App\LOCAL', 'PHP_EOL'], describeFetches($resolver, $fetches));
});


test('a function or a constant is written bare only where the bare name reaches it', function () use ($file, $app, $global) {
	$unknown = new NameResolver($file);
	Assert::same('\strlen', $unknown->getShortName('strlen', SymbolKind::Function, $app));
	Assert::same('\PHP_EOL', $unknown->getShortName('PHP_EOL', SymbolKind::Constant, $app));
	Assert::same('true', $unknown->getShortName('true', SymbolKind::Constant, $app)); // true, false and null never fall back
	Assert::same('\App\helper', $unknown->getShortName('App\helper', SymbolKind::Function, $app));
	Assert::same('imported', $unknown->getShortName('Lib\imported', SymbolKind::Function, $app));
	Assert::same('helper', $unknown->getShortName('helper', SymbolKind::Function, $global));

	$known = new NameResolver($file, new NamespacedSymbols(['App\helper'], ['App\VERSION'], complete: true));
	Assert::same('strlen', $known->getShortName('strlen', SymbolKind::Function, $app));
	Assert::same('PHP_EOL', $known->getShortName('PHP_EOL', SymbolKind::Constant, $app));
	Assert::same('helper', $known->getShortName('App\helper', SymbolKind::Function, $app));
	Assert::same('\helper', $known->getShortName('helper', SymbolKind::Function, $app)); // the bare name would reach App\helper
	Assert::same('VERSION', $known->getShortName('App\VERSION', SymbolKind::Constant, $app));
	Assert::same('\App\Version', $known->getShortName('App\Version', SymbolKind::Constant, $app));
	Assert::same('\Other\helper', $known->getShortName('Other\helper', SymbolKind::Function, $app));
});


test('the names of the symbols follow the letter case PHP gives them', function () {
	$symbols = new NamespacedSymbols(['\App\Helper'], ['App\Sub\VERSION']);
	Assert::true($symbols->hasFunction('app\HELPER'));
	Assert::true($symbols->hasConstant('\APP\sub\VERSION'));
	Assert::false($symbols->hasConstant('App\Sub\Version'));
	Assert::false($symbols->hasFunction('App\Sub\VERSION'));
	Assert::exception(fn() => new NamespacedSymbols(['helper']), InvalidArgumentException::class, "'helper' is not the fully qualified name of a symbol in a namespace.");
});
