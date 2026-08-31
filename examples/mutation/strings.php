<?php declare(strict_types=1);

/**
 * Rewriting a variable inside a string: a double-quoted string, a heredoc and a shell command, with
 * the braces written where the bare form would read the new expression otherwise, and the question
 * of what can stand in a string at all.
 *
 * Demonstrates: `ExpressionNode::replaceWithExpression()` inside interpolation, `canStandInString()`
 * Usage:        php examples/mutation/strings.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Builder;
use PhpSyntax\Nodes\Expression\{MethodCallNode, VariableNode};
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	$greeting = "Dear $name, you owe $total EUR.";
	$label = <<<TXT
		Order $id for $city
		TXT;
	$usage = `du -sh $dir`;
	PHP);

$file = new Parser()->parse($code);
$builder = new Builder;

// the codemod moves loose variables into the objects they came from
$replacements = [
	'name' => '$customer->name',
	'total' => '$order->getTotal()',
	'id' => '$order["id"]',
	'city' => '$customer->address->city',
	'dir' => '$this->root',
];

foreach ($file->find(VariableNode::class) as $variable) {
	if (isset($replacements[$variable->plainName])) {
		$variable->replaceWithExpression($builder->expression($replacements[$variable->plainName]));
	}
}

printDiff($code, (string) $file);

// what the grammar reads as a variable after `{$` can stand in a string, and nothing else can
echo "\n";
foreach (['$a', '$a->b()', '$a::$b', 'A::$b', 'count($a)', '$a * 2'] as $expression) {
	echo str_pad($expression, 12), var_export($builder->expression($expression)->canStandInString(), return: true), "\n";
}

// so a place in a string refuses anything else, before it changes a thing
$call = $file->findFirst(MethodCallNode::class);
try {
	$call->replaceWithExpression($builder->expression('$total * $rate'));
} catch (InvalidArgumentException $e) {
	echo "\nInvalidArgumentException: ", $e->getMessage(), "\n";
}
