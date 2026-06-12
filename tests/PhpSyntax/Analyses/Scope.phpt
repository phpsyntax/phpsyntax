<?php declare(strict_types=1);

use PhpSyntax\Analyses\Scope;
use PhpSyntax\{Node, Parser, Token};
use PhpSyntax\Nodes\Expression\{ClosureNode, VariableNode};
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


$code = <<<'XX'
	<?php
	$v0;
	function f(): int { $v1; $c = function () use ($a, &$b) { $v2; }; }
	class A {
		public $p { get => $v3; }
		public function m() { $v4; fn() => $v5; static fn() => $v6; function () { $v7; }; }
		public static function s() { $v8; function () { $v9; }; }
		public function n() { new class { function o() { $v10; } }; }
	}
	XX;

$file = (new Parser)->parse($code);
$scope = new Scope;


/** @return array<string, VariableNode> */
function variables(Node $file): array
{
	$result = [];
	foreach ($file->find(VariableNode::class) as $var) {
		if ($var->name instanceof Token && preg_match('~^\$v\d+$~', $var->name->text)) {
			$result[$var->name->text] = $var;
		}
	}

	return $result;
}


test('$this availability', function () use ($file, $scope) {
	$expected = [
		'$v0' => false, '$v1' => false, '$v2' => false, '$v3' => true, '$v4' => true, '$v5' => true, '$v6' => false, '$v7' => true,
		'$v8' => false, '$v9' => false, '$v10' => true,
	];
	$actual = [];
	foreach (variables($file) as $name => $var) {
		$actual[$name] = $scope->hasThis($var);
	}

	Assert::same($expected, $actual);
});


test('the variables a closure captures', function () use ($file) {
	$closures = $file->find(ClosureNode::class);
	Assert::same(['a', 'b'], $closures[0]->getCapturedVariableNames());
	Assert::same([], $closures[1]->getCapturedVariableNames());
});
