#!/usr/bin/env php
<?php declare(strict_types=1);

/**
 * Generates the reference: nodes.md from grammar/nodes.php and the node classes, the base classes
 * they build on first. Run by `composer reference`; the output is committed and CI diffs it.
 */

use PhpSyntax\LayoutData;

require __DIR__ . '/../../vendor/autoload.php';


/**
 * Text safe inside a table cell: a pipe would split it, a tag would be swallowed; inside a code span, which shows
 * an entity as it is written, only the pipe is escaped.
 */
function markdown(string $text): string
{
	return (string) preg_replace_callback(
		'~(`[^`]*`)|[^`]+~',
		fn(array $m) => str_replace(['|', '<'], ['\|', isset($m[1]) && $m[1] !== '' ? '<' : '&lt;'], $m[0]),
		$text,
	);
}


/**
 * The text of a docblock without its frame, with what a @var and a @throws say in words; a class whose doc comment
 * still says @todo, which the generator of the nodes writes into a new one, stops the reference.
 */
function describe(ReflectionClass|ReflectionMethod|ReflectionProperty $member): string
{
	$lines = [];
	foreach (preg_split('~\r\n|\r|\n~', (string) $member->getDocComment()) as $line) {
		$line = trim($line, " \t/*");
		if (str_starts_with($line, '@todo')) {
			throw new LogicException("{$member->getName()} is not described yet: " . $line);
		} elseif (preg_match('~^@var\s+.+?\s{2,}(.+)$~', $line, $m)) {
			$lines[] = $m[1];
		} elseif (preg_match('~^@throws\s+(.+?)(?:\s{2,}(.+))?$~', $line, $m)) {
			$lines[] = 'Throws ' . ltrim($m[1], '\\') . (isset($m[2]) ? ' ' . $m[2] : '') . '.';
		} elseif ($line !== '' && !str_starts_with($line, '@')) {
			$lines[] = $line;
		}
	}

	return implode(' ', $lines);
}


function isInternal(ReflectionMethod|ReflectionProperty $member): bool
{
	return str_contains((string) $member->getDocComment(), '@internal');
}


/** A class name relative to PhpSyntax\Nodes, or to PhpSyntax for the base classes. */
function relativeClassName(string $class): string
{
	return preg_replace('~^PhpSyntax\\\(Nodes\\\)?~', '', $class);
}


/** A type as a signature writes it, the class names relative. */
function typeName(?ReflectionType $type): string
{
	return $type === null
		? ''
		: preg_replace_callback('~[A-Za-z_\\\\][\w\\\\]*~', fn(array $match) => relativeClassName($match[0]), (string) $type);
}


/** The type of a property, taken from its @var where the native type is a bare array. */
function propertyType(ReflectionProperty $property): string
{
	$type = typeName($property->getType());
	return $type === 'array' && preg_match('~@var\s+(\S+)~', (string) $property->getDocComment(), $match)
		? $match[1]
		: $type;
}


function defaultValue(ReflectionParameter $parameter): string
{
	if ($parameter->isDefaultValueConstant()) {
		return relativeClassName((string) $parameter->getDefaultValueConstantName());
	}

	$value = $parameter->getDefaultValue();
	return match (true) {
		$value === null => 'null',
		$value === [] => '[]',
		default => var_export($value, return: true),
	};
}


function signature(ReflectionMethod $method): string
{
	$parameters = [];
	foreach ($method->getParameters() as $parameter) {
		$text = ($parameter->hasType() ? typeName($parameter->getType()) . ' ' : '')
			. ($parameter->isVariadic() ? '...' : '')
			. '$' . $parameter->getName();
		$parameters[] = $parameter->isDefaultValueAvailable() ? $text . ' = ' . defaultValue($parameter) : $text;
	}

	return ($method->isStatic() ? 'static ' : '')
		. $method->getName() . '(' . implode(', ', $parameters) . ')'
		. ($method->hasReturnType() ? ': ' . typeName($method->getReturnType()) : '');
}


/**
 * The public properties and methods the class itself declares, leaving out its slots, its constructor and
 * what is marked internal.
 * @param  ReflectionClass<object>  $class
 * @param  array<string, string>  $slots
 * @return list<array{string, string}>  signature and description
 */
function members(ReflectionClass $class, array $slots): array
{
	$members = [];
	foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
		if (
			$property->getDeclaringClass()->getName() === $class->getName()
			&& !isset($slots[$property->getName()])
			&& !$property->isPromoted()
			&& !isInternal($property)
		) {
			$members[] = ['$' . $property->getName() . ': ' . propertyType($property), describe($property)];
		}
	}

	foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
		if (
			$method->getDeclaringClass()->getName() === $class->getName()
			&& !in_array($method->getName(), ['__construct', '__clone'], true)
			&& !isInternal($method)
		) {
			$members[] = [signature($method), describe($method)];
		}
	}

	return $members;
}


/**
 * The section of one class: its description, what it extends and implements, its slots and its members.
 * @param  ReflectionClass<object>  $reflection
 * @param  array{slots?: array<string, string>, manual?: bool}  $node
 */
function section(ReflectionClass $reflection, array $node): string
{
	$out = '### ' . relativeClassName($reflection->getName()) . "\n\n";
	$description = describe($reflection);
	$out .= $description === '' ? '' : $description . "\n\n";
	$facts = [];
	if ($parent = $reflection->getParentClass()) {
		$facts[] = 'Extends `' . relativeClassName($parent->getName()) . '`';
	}

	$interfaces = array_filter($reflection->getInterfaceNames(), fn($i) => str_starts_with($i, 'PhpSyntax\\'));
	if ($interfaces) {
		$facts[] = 'Implements ' . implode(', ', array_map(fn($i) => '`' . relativeClassName($i) . '`', $interfaces));
	}

	if (!empty($node['manual'])) {
		$facts[] = 'Handwritten class';
	}

	if ($facts) {
		$out .= implode('. ', $facts) . ".\n\n";
	}

	if (!empty($node['slots'])) {
		$out .= "| Slot | Type | Layout |\n|---|---|---|\n";
		foreach ($node['slots'] as $slot => $type) {
			$role = LayoutData::Roles[$reflection->getName()][$slot];
			$out .= sprintf("| `%s` | %s | %s |\n", $slot, markdown("`$type`"), $role->name);
		}

		$out .= "\n";
	}

	if ($members = members($reflection, $node['slots'] ?? [])) {
		$out .= "| Member | Description |\n|---|---|\n";
		foreach ($members as [$signature, $description]) {
			$out .= sprintf("| %s | %s |\n", markdown("`$signature`"), markdown($description));
		}

		$out .= "\n";
	}

	return $out;
}


/** @var array<string, array{slots?: array<string, string>, manual?: bool}> $nodes */
$nodes = require __DIR__ . '/../../grammar/nodes.php';
$bases = [];
foreach ($nodes as $class => $node) {
	$name = 'PhpSyntax\Nodes\\' . $class;
	if (!class_exists($name)) {
		throw new Exception("The schema names $name, which does not exist; run composer compile-grammar.");
	}

	$reflection = new ReflectionClass($name);
	for ($parent = $reflection->getParentClass(); $parent; $parent = $parent->getParentClass()) {
		$bases[$parent->getName()] = $parent;
	}

	foreach ($reflection->getInterfaces() as $interface) {
		if (str_starts_with($interface->getName(), 'PhpSyntax\\')) {
			$bases[$interface->getName()] = $interface;
		}
	}
}

uksort($bases, fn(string $a, string $b) => [$a !== 'PhpSyntax\Node', $a] <=> [$b !== 'PhpSyntax\Node', $b]);

$out = "# Nodes\n\nGenerated by `composer reference` from grammar/nodes.php and the node classes; do not edit. "
	. count($nodes) . ' node classes under `PhpSyntax\Nodes` and the ' . count($bases) . ' base classes and interfaces they build on. '
	. 'A slot type is `Token`, a node class, `?` when optional, `A|B` for a choice, `NodeList<T>` or `SeparatedNodeList<T>` '
	. 'for a list; the layout role is what the slot is to the indentation of the lines the node spreads over '
	. '(`PhpSyntax\LayoutRole`). The members are the public properties and methods the class itself declares, '
	. "so what every node answers stands under `Node` and what every expression answers under `ExpressionNode`.\n\n"
	. "## Base classes\n\n";
foreach ($bases as $reflection) {
	$out .= section($reflection, []);
}

$out .= "## Node classes\n\n";
foreach ($nodes as $class => $node) {
	$out .= section(new ReflectionClass('PhpSyntax\Nodes\\' . $class), $node);
}
$out = trim($out) . "\n";

file_put_contents(__DIR__ . '/nodes.md', $out);
echo 'nodes.md: ', count($nodes), ' node classes, ', count($bases), " base classes\n";
