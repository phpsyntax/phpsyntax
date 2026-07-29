<?php declare(strict_types=1);

use PhpSyntax\Analyses\Scope;
use PhpSyntax\Node;
use PhpSyntax\Nodes\Expression\ClosureNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Member\PropertyHookNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\Statement\FunctionNode;
use PhpSyntax\Parser;
use PhpSyntax\Token;
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


test('enclosing function and class', function () use ($file, $scope) {
	$vars = variables($file);
	Assert::null($scope->getFunction($vars['$v0']));
	Assert::type(FunctionNode::class, $scope->getFunction($vars['$v1']));
	Assert::type(ClosureNode::class, $scope->getFunction($vars['$v2']));
	Assert::type(PropertyHookNode::class, $scope->getFunction($vars['$v3']));
	Assert::type(MethodNode::class, $scope->getFunction($vars['$v4']));
	Assert::null($scope->getClass($vars['$v1']));
	Assert::type(ClassNode::class, $scope->getClass($vars['$v4']));
	Assert::type(PhpSyntax\Nodes\AnonymousClassNode::class, $scope->getClass($vars['$v10']));

	// the interfaces say what every one of them has, so a caller reads it without asking which kind it is
	$hook = $scope->getFunction($vars['$v3']);
	Assert::null($hook->returnType);
	Assert::null($hook->parameters);
	Assert::same('int', $scope->getFunction($vars['$v1'])->returnType?->text);
	Assert::same('A', $scope->getClass($vars['$v4'])->name->text);

	$anonymous = $scope->getClass($vars['$v10']);
	Assert::null($anonymous->name);
	Assert::count(1, $anonymous->members);
});


test('$this availability', function () use ($file, $scope) {
	$expected = ['$v0' => false, '$v1' => false, '$v2' => false, '$v3' => true, '$v4' => true, '$v5' => true, '$v6' => false, '$v7' => true, '$v8' => false, '$v9' => false, '$v10' => true];
	$actual = [];
	foreach (variables($file) as $name => $var) {
		$actual[$name] = $scope->hasThis($var);
	}

	Assert::same($expected, $actual);
});


test('captured variables', function () use ($file, $scope) {
	$closures = $file->find(ClosureNode::class);
	Assert::same(['$a', '$b'], $scope->getCapturedVariables($closures[0]));
	Assert::same([], $scope->getCapturedVariables($closures[1]));
});
