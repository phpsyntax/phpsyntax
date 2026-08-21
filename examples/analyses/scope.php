<?php declare(strict_types=1);

/**
 * Where a node stands: the function and class around it, whether $this is available,
 * and what a closure captures.
 *
 * Demonstrates: Scope::getFunction(), getClass(), hasThis(), getCapturedVariables()
 * Usage:        php examples/analyses/scope.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Analyses\Scope;
use PhpSyntax\Nodes\Expression\ClosureNode;
use PhpSyntax\Nodes\Expression\VariableNode;
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
	PHP);

$file = new Parser()->parse($code);
$scope = new Scope;

printf("%-10s %-22s %-10s %s\n", 'variable', 'in', 'class', '$this available');
foreach ($file->find(VariableNode::class) as $variable) {
	$function = $scope->getFunction($variable);

	// the interface answers for every kind of class-like declaration; an anonymous class has no name
	$class = $scope->getClass($variable);

	printf(
		"%-10s %-22s %-10s %s\n",
		$variable->text,
		$function === null ? '-' : shortClass($function),
		$class?->name->text ?? '-',
		var_export($scope->hasThis($variable), return: true),
	);
}

echo "\n";
foreach ($file->find(ClosureNode::class) as $closure) {
	echo 'the closure captures: ', implode(', ', $scope->getCapturedVariables($closure)), "\n";
}
