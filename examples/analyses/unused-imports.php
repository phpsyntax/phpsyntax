<?php declare(strict_types=1);

/**
 * A real linter rule in twenty lines: find the imports nothing uses and delete them, and the
 * mirror operation, adding one.
 *
 * Demonstrates: NameResolver, `NameNode::$symbolKind`, `NameNode::isReference()`, `UseItemNode::$fullName`,
 *               `UseItemNode::remove()`, `UseNode::addImport()`
 * Usage:        php examples/analyses/unused-imports.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\Nodes\{NameNode, UseItemNode};
use PhpSyntax\Nodes\Statement\UseNode;
use PhpSyntax\{Parser, SymbolKind};

$code = sample(<<<'PHP'
	<?php
	namespace Shop\Billing;

	use Shop\Money;
	use Shop\Tax\Rate as TaxRate;
	use Shop\{Invoice, Receipt};
	use function Shop\format_money;

	class Calculator
	{
		public function total(Money $amount): Invoice
		{
			return new Invoice($amount);
		}
	}
	PHP);

$parser = new Parser;
$file = $parser->parse($code);
$resolver = new NameResolver($file);

// what the code refers to, fully qualified; isReference() leaves out the names the imports and the
// namespace introduce, self, static and parent, and builtin types such as string
$used = [];
foreach ($file->find(NameNode::class) as $name) {
	if ($name->symbolKind === SymbolKind::ClassLike && $name->isReference()) {
		$used[strtolower($resolver->resolveClass($name))] = true;
	}
}

// what it imports, and what of that nothing refers to. A group use writes the prefix once, on the
// statement, and $fullName is what the item imports whichever form it is written in.
foreach ($file->find(UseItemNode::class) as $item) {
	if ($item->symbolKind !== SymbolKind::ClassLike) {
		continue; // a function or a constant import; the same rule, over a different list of names
	}

	if (!isset($used[strtolower($item->fullName)])) {
		// line, because the report is about the file as it was read
		echo 'unused import on line ', $item->getFirstToken()->line, ': ', $item->fullName, "\n";
		// the only item of a statement takes the statement with it, a use with nothing to import being no code
		$item->remove();
	}
}

echo "\n";
printDiff($code, (string) $file);

// the mirror operation, on a fresh copy: an import is added by its fully qualified name, and the
// statement writes it the way it writes the rest of its items
$second = $parser->parse($code);
$group = $second->findFirst(UseNode::class, fn(UseNode $use) => $use->isGroup());
$group->addImport('Shop\Discount');
$group->addImport('Shop\Tax\Vat', alias: 'VatRate');

echo "\n";
printDiff($code, (string) $second);

// a group writes its items under its prefix, so a name from elsewhere has no place in it
try {
	$group->addImport('Billing\Ledger');
} catch (InvalidArgumentException $e) {
	echo "\n", 'InvalidArgumentException: ', $e->getMessage(), "\n";
}
