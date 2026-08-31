<?php declare(strict_types=1);

/**
 * Building new code where data decide what it is: a type of a cast from a table, an operator as a
 * string, a PHP array written as a literal, arguments named by the keys of an array.
 *
 * Demonstrates: `Builder::cast()`, `binary()`, `unary()`, `value()`, `call()`, `methodCall()`, `new()`,
 *               `constant()`, `variable()`
 * Usage:        php examples/mutation/building.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\Builder;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	function import(array $row): array
	{
		$id = intval($row['id']);
		$price = floatval($row['price']) * 1.21;
		$name = strval($row['name'] ?? '');   // a missing name is an empty one
		$color = sprintf('#%06x', intval($row['color'], 16));

		return [$id, $price, $name, $color];
	}
	PHP);

$file = new Parser()->parse($code);
$resolver = new NameResolver($file);
$builder = new Builder;

// the rule is a table, so the type of the cast is data: a template would have to be glued together
// from strings, while cast() takes the type as a value
$casts = ['intval' => 'int', 'floatval' => 'float', 'strval' => 'string', 'boolval' => 'bool'];
foreach ($file->find(FunctionCallNode::class) as $call) {
	$function = $resolver->findGlobalFunction($call, array_keys($casts));
	if ($function === null) {
		continue;
	}

	if (count($call->arguments->items) === 1) {
		$call->replaceWithExpression($builder->cast($casts[$function], $call->arguments->items[0]->value));
	} else {
		echo "left alone, a cast takes no base: $call->text\n\n";
	}
}

printDiff($code, (string) $file);

// an operator as a string: an operand gets parentheses where its side of the operator needs them,
// and a value that is no node is written as a literal
$sum = $builder->binary($builder->variable('a'), '+', $builder->variable('b'));
echo "\n";
echo $builder->binary($sum, '*', 2), "\n";
echo $builder->unary('-', $sum), "\n";

// a PHP array becomes an array literal, and a string key among the arguments names an argument
echo $builder->value(['host' => 'localhost', 'port' => 3306, 'debug' => false, 'tags' => ['db', 'eu']]), "\n";
echo $builder->call('json_encode', [$builder->variable('row'), 'flags' => $builder->constant('JSON_THROW_ON_ERROR')]), "\n";
echo $builder->methodCall($builder->new('Logger', ['import']), 'info', ['done', 'context' => ['rows' => 3]]), "\n";
