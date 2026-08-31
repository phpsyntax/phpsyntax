<?php declare(strict_types=1);

/**
 * Names as they are written: qualified or not, what they refer to, and how to take them apart.
 *
 * Demonstrates: `NameNode::$text`, `$form`, `$parts`, `$shortName`, `$symbolKind`, `isReference()`, `isKeyword()`, `equals()`
 * Usage:        php examples/tree/names.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Builder;
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	namespace Shop\Billing;

	use Shop\Money;

	function render(Money $amount, \DateTimeImmutable $due): string
	{
		return \sprintf('%s due %s', $amount, $due->format(DATE_ATOM));
	}
	PHP);

$file = new Parser()->parse($code);

printf("%-24s %-14s %-10s %-18s %s\n", 'written', 'form', 'role', 'short name', 'refers');
foreach ($file->find(NameNode::class) as $name) {
	printf(
		"%-24s %-14s %-10s %-18s %s\n",
		$name->text,
		$name->form->name,
		$name->symbolKind->name,
		$name->shortName,
		var_export($name->isReference(), return: true),
	);
}

// a name is one token, so a keyword standing where a name may stand is a name too
$type = new Builder()->type('static');
echo "\n", 'static is a name written as a keyword: ', var_export($type->findFirst(NameNode::class)->isKeyword(), return: true), "\n";

// a class and a condition in one question, and the predicate gets the node typed
$called = $file->findFirst(NameNode::class, fn(NameNode $name) => $name->shortName === 'sprintf');

// equals() compares the name as it is written, the leading backslash included; letter case is
// ignored where PHP ignores it, so this is the question "is this written the same way"
echo 'the call is written ', $called->text, "\n";
echo "  equals('\\SPRINTF'): ", var_export($called->equals('\SPRINTF'), return: true), "\n";
echo "  equals('sprintf'):   ", var_export($called->equals('sprintf'), return: true), "\n";
