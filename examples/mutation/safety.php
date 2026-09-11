<?php declare(strict_types=1);

/**
 * The questions a rewrite has to ask before it rewrites: is this expression the same as that one,
 * can it be read twice, is a comment in the way, and are these parentheses carrying meaning.
 *
 * Demonstrates: Node::matches(), isRepeatableRead(), hasComment(), ParenthesizedNode::isRedundant()
 * Usage:        php examples/mutation/safety.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\Expression\AssignmentNode;
use PhpSyntax\Nodes\Expression\BinaryOpNode;
use PhpSyntax\Nodes\Expression\ParenthesizedNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	$total = $total + $vat;
	$cart['sum'] = $cart['sum'] + $vat;
	$counts[$i++] = $counts[$i++] + 1;
	$total = $subtotal + $vat;
	$net = $net /* without VAT */ + $fee;

	$name = ($user->name);
	$date = (new DateTime)->format('Y');
	$out = ($factory)();
	$id = (FOO)::class;
	$sum = ($a + $b) * $c;
	$all = ($a + $b);
	PHP);

$file = new Parser()->parse($code);

// "$a = $a + $b" can become "$a += $b" only when both sides are the same expression, reading it
// twice has no side effects, and no comment sits in the part that would disappear
printf("%-38s %-6s %-12s %-8s %s\n", 'assignment', 'same', 'repeatable', 'comment', 'verdict');
foreach ($file->find(AssignmentNode::class) as $assign) {
	$var = $assign->target;
	$expr = $assign->expression;
	if (!$var instanceof ExpressionNode || !$expr instanceof BinaryOpNode || !$expr->operator->is('+')) {
		continue;
	}

	$same = $var->matches($expr->left);
	$repeatable = $var->isRepeatableRead();
	$comment = $assign->hasComment();
	printf(
		"%-38s %-6s %-12s %-8s %s\n",
		$assign->text,
		var_export($same, return: true),
		var_export($repeatable, return: true),
		var_export($comment, return: true),
		match (true) {
			!$same, !$repeatable => 'leave alone',
			$comment => 'leave alone (comment)',
			default => 'rewrite',
		},
	);
}

echo "\n";

// parentheses: isRedundant() weighs how tightly both sides bind, so precedence and dereferencing
// are one answer rather than two half-answers; getAccessKind() is the half that says which way
// the parent reaches in, because what may stand there bare differs by the kind
printf("%-16s %-12s %-12s %s\n", 'parentheses', 'redundant', 'reached by', 'why');
foreach ($file->find(ParenthesizedNode::class) as $parens) {
	printf(
		"%-16s %-12s %-12s %s\n",
		$parens->text,
		var_export($parens->isRedundant(), return: true),
		$parens->getAccessKind()->name ?? '-',
		match (true) {
			$parens->isRedundant() => 'nothing around them binds tighter',
			$parens->isDereferenced() => 'the parent reaches inside them',
			default => 'what stands around them binds at least as tightly',
		},
	);
}
