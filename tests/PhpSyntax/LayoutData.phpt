<?php declare(strict_types=1);

/**
 * The generated layout roles cover every slot of every node class, and nothing else: a slot the rule about
 * indentation does not know is a line it would leave alone without saying so.
 */

use PhpSyntax\LayoutData;
use PhpSyntax\Node;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$schema = require __DIR__ . '/../../grammar/nodes.php';
Assert::same(count($schema), count(LayoutData::Roles));

foreach ($schema as $name => $definition) {
	$class = 'PhpSyntax\Nodes\\' . $name;
	Assert::true(isset(LayoutData::Roles[$class]), $class);
	Assert::same(array_keys($definition['slots']), array_keys(LayoutData::Roles[$class]), $class);

	$reflection = new ReflectionClass($class);
	Assert::true($reflection->isSubclassOf(Node::class), $class);
	$slots = [];
	foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
		$type = $property->getType();
		// a slot holds tokens or nodes and has storage; a counter such as FileNode::$revision and a query
		// such as StringNode::$value are not slots
		if (
			!$property->isStatic()
			&& !$property->isVirtual()
			&& $property->getDeclaringClass()->getName() === $class
			&& !($type instanceof ReflectionNamedType && $type->isBuiltin() && $type->getName() !== 'array')
		) {
			$slots[] = $property->getName();
		}
	}

	Assert::same($slots, array_keys(LayoutData::Roles[$class]), $class);
}
