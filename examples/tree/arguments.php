<?php declare(strict_types=1);

/**
 * Which argument a parameter gets, however the call is written: positionally, by name, or not at all
 * because the call binds nothing and makes a closure instead.
 *
 * Demonstrates: ArgumentListNode::findArgument(), isPartialApplication(), VariableNode::$plainName
 * Usage:        php examples/tree/arguments.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	str_replace('a', 'b', $text);
	str_replace(subject: $text, search: 'a', replace: 'b');
	str_replace('a', 'b', subject: $text);
	str_replace(...$args);
	str_replace(...);
	str_replace('a', ?, $text);
	PHP);

$file = new Parser()->parse($code);

// str_replace(string|array $search, string|array $replace, string|array $subject): the third parameter
printf("%-54s %-14s %s\n", 'call', 'closure of it', 'subject argument');

foreach ($file->find(FunctionCallNode::class) as $call) {
	// a call that leaves parameters unbound is not a call, so no argument answers for a parameter
	$partial = $call->arguments->isPartialApplication();
	$subject = $partial ? null : $call->arguments->findArgument('subject', 2);

	printf(
		"%-54s %-14s %s\n",
		$call->text,
		var_export($partial, return: true),
		$subject?->value->text ?? '-',
	);
}

// the name of a variable without the dollar, which is what compares to a declared name; a variable
// whose name is an expression has none until the code runs, and says so instead of guessing
echo "\n";
printf("%-10s %s\n", 'written', 'plainName');
foreach (new Parser()->parse('<?php $total; $$name; ${$key};')->find(VariableNode::class) as $variable) {
	printf("%-10s %s\n", $variable->text, json_encode($variable->plainName));
}
