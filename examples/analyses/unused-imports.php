<?php declare(strict_types=1);

/**
 * A real linter rule in twenty lines: find the imports nothing uses and delete them, and the
 * mirror operation, adding one.
 *
 * Demonstrates: NameResolver, NameNode::$role, UseItemNode::$fullName, Node::remove(),
 *               UseNode::addImport()
 * Usage:        php examples/analyses/unused-imports.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\Statement\UseNode;
use PhpSyntax\Nodes\UseItemNode;
use PhpSyntax\Parser;
use PhpSyntax\SymbolKind;

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

// what the code refers to, fully qualified
$used = [];
foreach ($file->find(NameNode::class) as $name) {
	if ($name->role === SymbolKind::ClassLike && !$name->isDeclaration()) {
		$used[strtolower($resolver->resolveClass($name))] = true;
	}
}

// what it imports, and what of that nothing refers to. A group use writes the prefix once, on the
// statement, and $fullName is what the item imports whichever form it is written in.
foreach ($file->find(UseItemNode::class) as $item) {
	$statement = must($item->getStatement());
	if ($item->kind !== SymbolKind::ClassLike) {
		continue; // a function or a constant import; the same rule, over a different list of names
	}

	if (!isset($used[strtolower($item->fullName)])) {
		// originalLine, because the report is about the file as it was read
		echo 'unused import on line ', must($item->getFirstToken())->originalLine, ': ', $item->fullName, "\n";
		count($statement->items) === 1 ? $statement->remove() : $item->remove();
	}
}

echo "\n";
printDiff($code, (string) $file);

// the mirror operation, on a fresh copy: an import is added by its fully qualified name, and the
// statement writes it the way it writes the rest of its items
$second = $parser->parse($code);
$group = must($second->findFirst(UseNode::class, fn(UseNode $use) => $use->isGroup()));
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

// a rule shipped to users would also count a class named in a doc comment, which is a question
// about text: the tree hands the comment over and stays out of what it means
