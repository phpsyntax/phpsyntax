<?php declare(strict_types=1);

/**
 * The classes that narrow `getFirstToken()` and `getLastToken()` to `Token` always have one, and those that always
 * have one narrow them.
 */

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\{ExpressionNode, FileNode, MemberNode, ModifiersNode, NameNode, PlainNodeList, SeparatedNodeList, SkippedArrayItemNode, StatementNode, TypeNode};
use PhpSyntax\Nodes\Member\{MethodNode, PropertyNode};
use PhpSyntax\Nodes\Type\{NullableTypeNode, UnionTypeNode};
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


/** Whether the class or one of its parents carries the annotation of both methods. */
function narrowsTokens(string $class): bool
{
	if (!class_exists($class)) {
		return false;
	}

	for ($reflection = new ReflectionClass($class); $reflection; $reflection = $reflection->getParentClass()) {
		$doc = (string) $reflection->getDocComment();
		if (str_contains($doc, '@method Token getFirstToken()') && str_contains($doc, '@method Token getLastToken()')) {
			return true;
		}
	}

	return false;
}


/** @return list<class-string<Node>> */
function nodeClasses(): array
{
	$classes = [];
	foreach (array_keys(require __DIR__ . '/../../../grammar/nodes.php') as $class) {
		$class = 'PhpSyntax\Nodes\\' . $class;
		if (is_a($class, Node::class, allow_string: true)) {
			$classes[] = $class;
		}
	}

	return $classes;
}


test('the annotation stands on the classes with a required token', function () {
	foreach ([ExpressionNode::class, StatementNode::class, NameNode::class, FileNode::class, MethodNode::class, NullableTypeNode::class] as $class) {
		Assert::true(narrowsTokens($class), $class);
	}

	foreach ([
		Node::class, MemberNode::class, PropertyNode::class, TypeNode::class, UnionTypeNode::class, ModifiersNode::class,
		PlainNodeList::class, SeparatedNodeList::class, SkippedArrayItemNode::class,
	] as $class) {
		Assert::false(narrowsTokens($class), $class);
	}
});


test('a class narrowing the tokens has a required slot holding a token or a node that narrows them too', function () {
	foreach (nodeClasses() as $class) {
		if (!narrowsTokens($class)) {
			continue;
		}

		$certain = false;
		foreach ($class::Slots as $slot) {
			$type = new ReflectionProperty($class, $slot)->getType();
			$types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];
			if (
				$type !== null
				&& !$type->allowsNull()
				&& array_all($types, fn($type) => $type instanceof ReflectionNamedType && ($type->getName() === Token::class || narrowsTokens($type->getName())))
			) {
				$certain = true;
				break;
			}
		}

		Assert::true($certain, $class);
	}
});


test('a class with a required slot holding a token or a node that narrows them narrows them too', function () {
	// every token of these stands in a list a mutation may empty, or they have none
	$exceptions = [
		'Member\PropertyNode', 'Type\UnionTypeNode', 'Type\IntersectionTypeNode', 'SkippedArrayItemNode', 'PlainNodeList',
		'SeparatedNodeList', 'ModifiersNode',
	];
	$schema = require __DIR__ . '/../../../grammar/nodes.php';
	$resolve = fn(string $name) => class_exists('PhpSyntax\Nodes\\' . $name) ? 'PhpSyntax\Nodes\\' . $name : 'PhpSyntax\\' . $name;
	$required = [];
	do {
		$changed = false;
		foreach ($schema as $name => $definition) {
			if (isset($required[$name]) || in_array($name, $exceptions, true)) {
				continue;
			}

			foreach ($definition['slots'] ?? [] as $type) {
				if (
					!str_starts_with($type, '?')
					&& !str_contains($type, '<')
					&& array_all(explode('|', $type), fn(string $part) => $part === 'Token' || isset($required[$part]) || narrowsTokens($resolve($part)))
				) {
					$required[$name] = $changed = true;
					break;
				}
			}
		}
	} while ($changed);

	Assert::notSame([], $required);
	foreach (array_keys($required) as $name) {
		Assert::true(narrowsTokens($resolve($name)), $name);
	}
});


test('a token is its own first and last token', function () {
	$token = new Token(Token::Variable, '$a');
	Assert::same($token, $token->getFirstToken());
	Assert::same($token, $token->getLastToken());
});
