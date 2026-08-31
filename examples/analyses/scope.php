<?php declare(strict_types=1);

/**
 * Where a node stands: the function and class around it, whether `$this` is available,
 * and what a closure captures.
 *
 * Demonstrates: `Node::findAncestor()`, `Scope::hasThis()`, `ClosureNode::getCapturedVariableNames()`
 * Usage:        php examples/analyses/scope.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Analyses\Scope;
use PhpSyntax\Nodes\{ClassLikeNode, FunctionLikeNode};
use PhpSyntax\Nodes\Expression\{ClosureNode, VariableNode};
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	class Report
	{
		public function render(array $rows): string
		{
			$prefix = '#';
			$format = function (array $row) use ($prefix) {
				return $prefix . $this->escape($row['label']);
			};
			$plain = static fn(array $row) => $row['label'];
			return implode("\n", array_map($format, $rows)) . self::footer();
		}

		public static function footer(): string
		{
			return '--';
		}
	}

	$printer = new class {
		public function print(string $line): void
		{
			echo $line;
		}
	};
	PHP);

$file = new Parser()->parse($code);
$scope = new Scope;

printf("%-10s %-22s %-10s %s\n", 'variable', 'in', 'class', '$this available');
foreach ($file->find(VariableNode::class) as $variable) {
	$function = $variable->findAncestor(FunctionLikeNode::class);

	// the interface answers for every kind of class-like declaration; an anonymous class has no name
	$class = $variable->findAncestor(ClassLikeNode::class);

	printf(
		"%-10s %-22s %-10s %s\n",
		$variable->text,
		$function === null ? '-' : shortClass($function),
		$class === null ? '-' : $class->name->text ?? 'anonymous',
		var_export($scope->hasThis($variable), return: true),
	);
}

echo "\n";
foreach ($file->find(ClosureNode::class) as $closure) {
	echo 'the closure captures: ', implode(', ', $closure->getCapturedVariableNames()), "\n";
}
