<?php declare(strict_types=1);

/**
 * Setters of slots that come with a token of their own: the return type with its colon, the type of a
 * parameter and of a property with the space after it.
 */

use PhpSyntax\{Builder, Node, Parser};
use PhpSyntax\Nodes\Expression\{ArrowFunctionNode, ClosureNode};
use PhpSyntax\Nodes\{FunctionLikeNode, ParameterNode};
use PhpSyntax\Nodes\Member\{MethodNode, PropertyHookNode, PropertyNode};
use PhpSyntax\Nodes\Statement\FunctionNode;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


/**
 * @template T of Node
 * @param  class-string<T>  $class
 * @return T
 */
function first(Node $root, string $class): Node
{
	return $root->findFirst($class) ?? throw new LogicException("No $class.");
}


test('a return type is written with its colon and the gap before the body stays', function () {
	$parser = new Parser;
	$builder = new Builder;
	$file = $parser->parse("<?php\nfunction f()\n{\n}\nfunction g() {}\n");
	$functions = $file->find(FunctionNode::class);
	$functions[0]->setReturnType($builder->type('int'));
	$functions[1]->setReturnType($builder->type('?string'));
	Assert::same("<?php\nfunction f(): int\n{\n}\nfunction g(): ?string {}\n", (string) $file);
	Assert::same('int', $functions[0]->returnType?->text);
	Assert::same(5, $functions[1]->body->getFirstToken()->getCurrentLine());

	// a type already there is replaced in place, the trivia around it staying
	$functions[1]->setReturnType($builder->type('array'));
	Assert::same("<?php\nfunction f(): int\n{\n}\nfunction g(): array {}\n", (string) $file);

	// removed, the colon goes too and the anchor gets the gap back
	$functions[0]->setReturnType(null);
	$functions[1]->setReturnType(null);
	Assert::same("<?php\nfunction f()\n{\n}\nfunction g() {}\n", (string) $file);
	Assert::null($functions[0]->colon);
	$functions[1]->setReturnType(null);
	Assert::same("<?php\nfunction f()\n{\n}\nfunction g() {}\n", (string) $file);

	$spaced = $builder->statement('function h() : int {}');
	assert($spaced instanceof FunctionNode);
	Assert::same('function h() {}', (string) $spaced->setReturnType(null));
});


test('a removed return type leaves its comments behind and closes the gap', function () {
	$remove = function (string $code): string {
		$file = new Parser()->parse($code);
		$revision = $file->revision;
		($file->findFirst(FunctionLikeNode::class) ?? throw new LogicException)->setReturnType(null);
		return (string) $file . ($file->revision === $revision ? ' (unchanged)' : '');
	};

	Assert::same('<?php function f() /* c */ {}', $remove('<?php function f(): /* c */ int {}'));
	Assert::same('<?php function f() /* c */ {}', $remove('<?php function f() : int /* c */ {}'));
	Assert::same('<?php function f() /* x */ {}', $remove('<?php function f(): int|/* x */string {}'));
	Assert::same("<?php function f() // c\n{}", $remove("<?php function f(): int // c\n{}"));
	Assert::same("<?php function f()\n{}", $remove("<?php function f()\n\t: int\n{}"));
	Assert::same("<?php function f() // c\n{}", $remove("<?php function f() // c\n: int\n{}"));
	Assert::same('<?php abstract class A { abstract function f() /* c */; }', $remove('<?php abstract class A { abstract function f(): /* c */ int; }'));
	Assert::same('<?php interface I { function f(); }', $remove('<?php interface I { function f(): int; }'));
	Assert::same('<?php interface I { function f() /* c */; }', $remove('<?php interface I { function f(): int /* c */; }'));
	Assert::same('<?php $f = fn() => 1;', $remove('<?php $f = fn(): int => 1;'));
	Assert::same('<?php function f() {} (unchanged)', $remove('<?php function f() {}'));
});


test('a removed type of a parameter or a property leaves its comments behind and closes the gap', function () {
	$remove = function (string $code): string {
		$file = new Parser()->parse($code);
		$revision = $file->revision;
		($file->findFirst(PropertyNode::class) ?? first($file, ParameterNode::class))->setType(null);
		return (string) $file . ($file->revision === $revision ? ' (unchanged)' : '');
	};

	Assert::same('<?php function f(/* c */ $a) {}', $remove('<?php function f(int /* c */ $a) {}'));
	Assert::same("<?php function f(\n\t\$a) {}", $remove("<?php function f(\n\tint\n\t\$a) {}"));
	Assert::same("<?php function f(\n\t// c\n\t\$a) {}", $remove("<?php function f(\n\tint // c\n\t\$a) {}"));
	Assert::same("<?php function f(\n\t\$a) {}", $remove("<?php function f(int\n\t\$a) {}"));
	Assert::same('<?php function f(#[A] $a) {}', $remove('<?php function f(#[A] int $a) {}'));
	Assert::same('<?php class A { public /* c */ $a; }', $remove('<?php class A { public int /* c */ $a; }'));
	Assert::same('<?php class A { public $a { get => 1; } }', $remove('<?php class A { public int $a { get => 1; } }'));
	Assert::same('<?php function f($a) {} (unchanged)', $remove('<?php function f($a) {}'));
});


test('every function-like construct but a hook takes a return type', function () {
	$parser = new Parser;
	$builder = new Builder;
	$file = $parser->parse("<?php\nclass A {\n\tpublic function m() {}\n\tpublic int \$p { get => 1; }\n}\n\$c = function () use (\$a) {};\n\$f = fn(\$x) => \$x;\n");
	first($file, MethodNode::class)->setReturnType($builder->type('void'));
	first($file, ClosureNode::class)->setReturnType($builder->type('int'));
	first($file, ArrowFunctionNode::class)->setReturnType($builder->type('int'));
	Assert::same("<?php\nclass A {\n\tpublic function m(): void {}\n\tpublic int \$p { get => 1; }\n}\n\$c = function () use (\$a): int {};\n\$f = fn(\$x): int => \$x;\n", (string) $file);

	$hook = first($file, PropertyHookNode::class);
	Assert::exception(fn() => $hook->setReturnType(null), LogicException::class, 'A property hook has no return type.');
});


test('a type standing in a tree is copied, a detached one taken as it is', function () {
	$parser = new Parser;
	$builder = new Builder;
	$source = $parser->parse("<?php\nfunction a(): /* c */ int {}\n");
	$type = first($source, FunctionNode::class)->returnType ?? throw new LogicException;
	$function = $builder->statement('function f() {}');
	assert($function instanceof FunctionNode);
	$function->setReturnType($type);
	Assert::same('function f(): int {}', (string) $function);
	Assert::notSame($type, $function->returnType);
	Assert::same("<?php\nfunction a(): /* c */ int {}\n", (string) $source);

	$detached = $builder->type('string');
	$function->setReturnType($detached);
	Assert::same($detached, $function->returnType);
	Assert::same('function f(): string {}', (string) $function);
	$function->setReturnType($detached);
	Assert::same('function f(): string {}', (string) $function);
});


test('the type of a parameter stands before it with a space', function () {
	$parser = new Parser;
	$builder = new Builder;
	$file = $parser->parse("<?php\nfunction f(\$a, &\$b, ...\$c) {}\nclass A {\n\tfunction __construct(\n\t\tprivate \$d,\n\t\t\$e,\n\t) {}\n}\n");
	foreach ($file->find(ParameterNode::class) as $parameter) {
		$parameter->setType($builder->type('int'));
	}

	Assert::same("<?php\nfunction f(int \$a, int &\$b, int ...\$c) {}\nclass A {\n\tfunction __construct(\n\t\tprivate int \$d,\n\t\tint \$e,\n\t) {}\n}\n", (string) $file);

	foreach ($file->find(ParameterNode::class) as $parameter) {
		$parameter->setType(null);
	}

	Assert::same("<?php\nfunction f(\$a, &\$b, ...\$c) {}\nclass A {\n\tfunction __construct(\n\t\tprivate \$d,\n\t\t\$e,\n\t) {}\n}\n", (string) $file);
	Assert::same(6, $file->find(ParameterNode::class)[4]->getFirstToken()->getCurrentLine());
});


test('the type of a property stands before its items with a space', function () {
	$parser = new Parser;
	$builder = new Builder;
	$file = $parser->parse("<?php\nclass A {\n\tpublic \$a;\n\tvar \$b, \$c = 1;\n\tpublic ?int \$d;\n}\n");
	$properties = $file->find(PropertyNode::class);
	$properties[0]->setType($builder->type('int'));
	$properties[1]->setType($builder->type('string|int'));
	$properties[2]->setType($builder->type('float'));
	Assert::same("<?php\nclass A {\n\tpublic int \$a;\n\tvar string|int \$b, \$c = 1;\n\tpublic float \$d;\n}\n", (string) $file);

	foreach ($properties as $property) {
		$property->setType(null);
	}

	Assert::same("<?php\nclass A {\n\tpublic \$a;\n\tvar \$b, \$c = 1;\n\tpublic \$d;\n}\n", (string) $file);
});
