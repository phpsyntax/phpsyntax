<?php declare(strict_types=1);

/**
 * Building new code from a template: PHP text with named placeholders, filled with nodes you already
 * hold. The builder copies what still stands in the file and writes the parentheses an operator needs.
 *
 * Demonstrates: `Builder::expression()`, `statement()`, `value()`, placeholders, copies, parentheses
 * Usage:        php examples/mutation/builder.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Builder;
use PhpSyntax\Nodes\Expression\{AssignmentNode, IssetNode, TernaryNode};
use PhpSyntax\Nodes\Statement\ReturnNode;
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	function invoice(array $cart, float $rate): string
	{
		$net = $cart['items'] + $cart['shipping'];   // shipping is taxed too
		$label = isset($cart['label']) ? $cart['label'] : 'Invoice';

		return format_money($net);
	}
	PHP);

$file = new Parser()->parse($code);
$builder = new Builder;

// 1. a shape is a template: isset($a) ? $a : $b is $a ?? $b, and the two variables named as
//    placeholders are replaced by the nodes given
$ternary = $file->findFirst(TernaryNode::class, fn(TernaryNode $node) => $node->condition instanceof IssetNode);
$ternary->replaceWith($builder->expression('$value ?? $default', value: $ternary->then, default: $ternary->else));

// 2. a placeholder under an operator: the sum lands under `*` and gets the parentheses that keep it
//    a sum; `$rate` is named as no placeholder, so it stays the variable it is
$sum = $file->findFirst(AssignmentNode::class)->expression;
$gross = $builder->expression('$net * (1 + $rate)', net: $sum);
echo 'built:            ', $gross, "\n";

// the sum still stands in the file, so the builder took a copy; nothing changes until you put the result in
echo 'the sum is still: ', $sum, "\n";
$sum->replaceWith($gross);

// 3. a statement is a template too; a placeholder takes a node, and value() writes a PHP value as one,
//    here a setting the tool was given
$settings = ['currency' => 'EUR'];
$return = $file->findFirst(ReturnNode::class);
$return->replaceWith($builder->statement(
	'return format_money($amount, $currency);',
	amount: $return->expression->arguments->items[0]->value,
	currency: $builder->value($settings['currency']),
));

echo "\n";
printDiff($code, (string) $file);

// 4. a placeholder the template does not have is a typo, and the builder says so before it builds anything
try {
	$builder->expression('$net * $rate', nett: $sum);
} catch (InvalidArgumentException $e) {
	echo "\nInvalidArgumentException: ", $e->getMessage(), "\n";
}

// and so is one its place cannot hold: the parameter of a closure takes a variable, not a property
try {
	$builder->expression('fn($amount) => $amount * 2', amount: $builder->expression('$cart->total'));
} catch (InvalidArgumentException $e) {
	echo "\nInvalidArgumentException: ", $e->getMessage(), "\n";
}
