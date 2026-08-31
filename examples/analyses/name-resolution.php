<?php declare(strict_types=1);

/**
 * Resolving names the way PHP does: against the namespace, the imports and the global fallback.
 *
 * Demonstrates: `NameResolver::resolve()`, `findGlobalFunction()`, `getUnqualifiedResolution()`,
 *               `shortenName()`, `isAliasFree()`, `NameNode::isReference()`
 * Usage:        php examples/analyses/name-resolution.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\{Parser, SymbolKind};

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
	if (!$name->isReference()) {
		continue; // the imports and the namespace declaration themselves, and builtin types such as string
	}

	// the place tells a class, a function and a constant apart, and resolve() follows it
	printf("%-16s %-10s %s\n", $name->text, $name->symbolKind->name, $resolver->resolve($name));
}

// "which of these global functions does the call call" is the question a rule about built-ins asks,
// and whether PHP's fallback to the global namespace is certain is the one it asks next
echo "\n";
foreach ($file->find(FunctionCallNode::class) as $call) {
	echo str_pad($call->text, 22), ' calls ', $resolver->findGlobalFunction($call, ['strtoupper', 'format_money']) ?? 'none of them',
		', ', $resolver->getUnqualifiedResolution($call->name)->name, "\n";
}

// and the way back: the shortest way to write a fully qualified name where a node stands
echo "\n";
$at = $file->findFirst(NameNode::class, fn(NameNode $name) => $name->equals('Money'));
foreach (['Shop\Money', 'Shop\Tax\Rate', 'Shop\Billing\Invoice', 'DateTimeImmutable'] as $fullName) {
	echo str_pad($fullName, 22), ' written here as ', $resolver->shortenName($fullName, SymbolKind::ClassLike, $at), "\n";
}

echo "\n", 'the alias Money is free here: ', var_export($resolver->isAliasFree('Money', SymbolKind::ClassLike, $at), return: true), "\n";
echo 'the alias Invoice is free here: ', var_export($resolver->isAliasFree('Invoice', SymbolKind::ClassLike, $at), return: true), "\n";
