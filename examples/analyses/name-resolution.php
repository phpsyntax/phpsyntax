<?php declare(strict_types=1);

/**
 * Resolving names the way PHP does: against the namespace, the imports and the global fallback.
 *
 * Demonstrates: NameResolver::resolveClass(), resolveFunction(), resolveConstant(),
 *               isGlobalFunctionCall(), getShortName(), isAliasFree()
 * Usage:        php examples/analyses/name-resolution.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\Type\NamedTypeNode;
use PhpSyntax\Parser;
use PhpSyntax\SymbolKind;

$code = sample(<<<'PHP'
	<?php
	namespace Shop\Billing;

	use Shop\Money;
	use Shop\Tax\Rate as TaxRate;
	use function Shop\format_money;

	function total(Money $amount, TaxRate $rate): string
	{
		$sum = $amount->multiply($rate);
		return format_money($sum) . ' ' . strtoupper(CURRENCY) . Helper::SUFFIX;
	}
	PHP);

$file = new Parser()->parse($code);
$resolver = new NameResolver($file);

printf("%-16s %-10s %s\n", 'written', 'role', 'resolves to');
foreach ($file->find(NameNode::class) as $name) {
	$parent = $name->parent;
	if ($name->isDeclaration()) {
		continue; // the imports and the namespace declaration themselves
	}

	if ($parent instanceof NamedTypeNode && $parent->isBuiltin()) {
		continue; // int, string, array and friends are types, not class names
	}

	printf("%-16s %-10s %s\n", $name->text, $name->role->name, match ($name->role) {
		SymbolKind::Function => $resolver->resolveFunction($name),
		SymbolKind::Constant => $resolver->resolveConstant($name),
		default => $resolver->resolveClass($name),
	});
}

// "is this a call of the global function X" is the question a rule about built-ins asks
echo "\n";
foreach ($file->find(FunctionCallNode::class) as $call) {
	echo $call->text, ' is a global strtoupper(): ',
		var_export($resolver->isGlobalFunctionCall($call, 'strtoupper'), return: true), "\n";
}

// and the way back: the shortest way to write a fully qualified name where a node stands
echo "\n";
$at = must($file->findFirst(NameNode::class, fn(NameNode $name) => $name->equals('Money')));
foreach (['Shop\Money', 'Shop\Tax\Rate', 'Shop\Billing\Invoice', 'DateTimeImmutable'] as $fullName) {
	echo str_pad($fullName, 22), ' written here as ', $resolver->getShortName($fullName, SymbolKind::ClassLike, $at), "\n";
}

echo "\n", 'the alias Money is free here: ', var_export($resolver->isAliasFree('Money', SymbolKind::ClassLike, $at), return: true), "\n";
echo 'the alias Invoice is free here: ', var_export($resolver->isAliasFree('Invoice', SymbolKind::ClassLike, $at), return: true), "\n";
