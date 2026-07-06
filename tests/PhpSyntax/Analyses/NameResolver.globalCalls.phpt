<?php declare(strict_types=1);

/**
 * The question "is this a call of a global function that should be imported or fully qualified"
 * answered with the resolver in a few lines, for a set of functions the PHP compiler optimizes.
 */

use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\NameKind;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Parser;
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
		&& $name->kind === NameKind::Unqualified
		&& $resolver->getNamespace($call) !== ''
		&& in_array(strtolower($name->text), $optimized, strict: true)
		&& $resolver->isGlobalFunctionCall($call)
		&& !isset($resolver->getFunctionImports($call)[strtolower($name->text)])
	) {
		$unoptimized[] = $name->text . ' on line ' . $name->token->originalLine;
	}
}

Assert::same(['strlen on line 4', 'in_array on line 4'], $unoptimized);
