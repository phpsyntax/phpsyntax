<?php declare(strict_types=1);

/**
 * Literals keep both the value and the way it is written: 0x1F stays 0x1F, and knows it is 31.
 *
 * Demonstrates: IntegerNode::$value/$base, FloatNode, StringNode::$value/$quote,
 *               HeredocNode::isNowdoc()/hasInterpolation(), ScalarNode, BooleanNode, NullNode
 * Usage:        php examples/tree/literals.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\Scalar\FloatNode;
use PhpSyntax\Nodes\Scalar\HeredocNode;
use PhpSyntax\Nodes\Scalar\IntegerNode;
use PhpSyntax\Nodes\Scalar\StringNode;
use PhpSyntax\Nodes\ScalarNode;
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	$flags = [0x1F, 0b1010, 0o17, 017, 1_000_000];
	$rate = 1.5e-3;
	$name = 'O\'Brien';
	$path = "C:\\temp";
	$note = <<<TXT
		indented body
		TXT;
	$query = <<<SQL
		SELECT * FROM orders WHERE id = {$id}
		SQL;
	$regex = <<<'RE'
		\d+\s*$total
		RE;
	PHP);

$file = new Parser()->parse($code);

printf("%-8s %-12s %-8s %s\n", 'literal', 'as written', 'detail', 'value');

foreach ($file->find(IntegerNode::class) as $int) {
	printf("%-8s %-12s %-8s %d\n", 'integer', $int->token->text, 'base ' . $int->base, $int->value);
}

foreach ($file->find(FloatNode::class) as $float) {
	printf("%-8s %-12s %-8s %s\n", 'float', $float->token->text, '', $float->value);
}

foreach ($file->find(StringNode::class) as $string) {
	printf("%-8s %-12s %-8s %s\n", 'string', $string->token->text, 'quote ' . $string->quote, $string->value);
}

// a heredoc with interpolation has no single string value, and says so instead of guessing;
// a nowdoc interpolates nothing whatever stands in it, which is why it is asked separately
foreach ($file->find(HeredocNode::class) as $heredoc) {
	printf(
		"%-8s %-12s %-8s %s\n",
		$heredoc->isNowdoc() ? 'nowdoc' : 'heredoc',
		$heredoc->label,
		'indent ' . json_encode($heredoc->indentation),
		$heredoc->hasInterpolation() ? '(interpolated, no single value)' : json_encode($heredoc->value),
	);
}

// true, false and null are literals of their own, so a name is the only thing left that needs resolving
echo "\n";
$parser = new Parser;
printf("%-12s %-24s %s\n", 'written', 'node', 'kind');
foreach (['true', 'FALSE', '\null', 'PHP_EOL', '__LINE__'] as $source) {
	$expr = $parser->parseExpression($source);
	printf("%-12s %-24s %s\n", $source, shortClass($expr), $expr instanceof ScalarNode ? 'literal' : 'name to resolve');
}

// a whole expression written as a value, arrays and nesting included, is read the same way
echo "\n";
foreach (["['id' => 7, 'tags' => ['a', 'b'], 'live' => true]", '"$prefix-1"', 'PHP_INT_MAX', '1 + 2'] as $source) {
	$expr = $parser->parseExpression($source);
	printf(
		"%-44s %s\n",
		$source,
		$expr->hasValue() ? json_encode($expr->toValue()) : 'written as no value',
	);
}
