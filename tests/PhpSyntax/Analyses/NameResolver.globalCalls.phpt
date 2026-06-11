<?php declare(strict_types=1);

/**
 * The question "is this a call of a global function that should be imported or fully qualified"
 * answered with the resolver in a few lines, for a set of functions the PHP compiler optimizes.
 */

use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\{NameForm, Parser, SymbolKind};
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\NameNode;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


$optimized = ['strlen', 'count', 'is_array', 'in_array'];

$file = (new Parser)->parse(<<<'XX'
	<?php
	namespace App;
	use function count;
	strlen($a); \strlen($a); count($a); in_array($a, $b); is_array($a); helper($a); $f($a); Foo::strlen(); $o->count();
	function is_array() {}
	namespace {
		strlen($a);
	}
	XX);

$resolver = new NameResolver($file);
$unoptimized = [];
foreach ($file->find(FunctionCallNode::class) as $call) {
	$name = $call->name;
	if (
		$name instanceof NameNode
		&& $name->form === NameForm::Unqualified
		&& $resolver->getNamespace($call) !== ''
		&& in_array(strtolower($name->text), $optimized, strict: true)
		&& $resolver->isGlobalFunctionCall($call)
		&& !isset($resolver->getImports(SymbolKind::Function, $call)[strtolower($name->text)])
	) {
		$unoptimized[] = $name->text . ' on line ' . $name->token->line;
	}
}

Assert::same(['strlen on line 4', 'in_array on line 4'], $unoptimized);


// which of the functions the call calls, as given; a namespaced one and a call not made by a name calls none
$found = array_map(fn(FunctionCallNode $call) => $resolver->findGlobalFunction($call, ['STRLEN', 'count', 'is_array']), $file->find(FunctionCallNode::class));
Assert::same(['STRLEN', 'STRLEN', 'count', null, null, null, null, 'STRLEN'], $found);
Assert::null($resolver->findGlobalFunction($file->find(FunctionCallNode::class)[0], new ArrayIterator([])));

// a string key names the function, an integer one leaves it to the value
$found = array_map(fn(FunctionCallNode $call) => $resolver->findGlobalFunction($call, ['STRLEN' => 'mb_strlen', 'count' => 1, 2 => 'is_array']), $file->find(FunctionCallNode::class));
Assert::same(['STRLEN', 'STRLEN', 'count', null, null, null, null, 'STRLEN'], $found);
Assert::null($resolver->findGlobalFunction($file->find(FunctionCallNode::class)[0], ['mb_strlen' => 'strlen']));

// resolve() goes by the kind of symbol the name stands for
$names = array_map(fn(NameNode $name) => $resolver->resolve($name), $file->find(NameNode::class, fn(NameNode $name) => $name->isReference()));
Assert::same(['strlen', 'strlen', 'count', 'in_array', 'App\is_array', 'helper', 'App\Foo', 'strlen'], $names);
