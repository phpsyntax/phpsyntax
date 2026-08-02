<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\AccessKind;
use PhpSyntax\Node;
use PhpSyntax\Nodes\Expression\ArrayAccessNode;
use PhpSyntax\Nodes\Expression\ClassConstantFetchNode;
use PhpSyntax\Nodes\Expression\ConstantFetchNode;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Expression\MethodCallNode;
use PhpSyntax\Nodes\Expression\ParenthesizedNode;
use PhpSyntax\Nodes\Expression\PropertyFetchNode;
use PhpSyntax\Nodes\Expression\StaticMethodCallNode;
use PhpSyntax\Nodes\Expression\StaticPropertyFetchNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\Scalar\BooleanNode;
use PhpSyntax\Nodes\Scalar\NullNode;
use function is_array, is_float, is_int, is_string;


abstract class ExpressionNode extends Node
{
	/**
	 * How the parent reaches into this expression, null where it does not: `$this->x` and `$this[0]` read
	 * a member, `$this()` calls it, `$this::y()` takes it for the name of a class.
	 */
	public function getAccessKind(): ?AccessKind
	{
		$parent = $this->parent;
		return match (true) {
			$parent instanceof MethodCallNode, $parent instanceof PropertyFetchNode => $parent->object === $this ? AccessKind::Member : null,
			$parent instanceof ArrayAccessNode => $parent->expression === $this ? AccessKind::Member : null,
			$parent instanceof FunctionCallNode => $parent->name === $this ? AccessKind::Call : null,
			$parent instanceof StaticMethodCallNode, $parent instanceof StaticPropertyFetchNode, $parent instanceof ClassConstantFetchNode => $parent->class === $this ? AccessKind::ClassName : null,
			default => null,
		};
	}


	/**
	 * Whether the parent reads a member, an element or a static member of this expression, or calls it:
	 * `$this->x`, `$this[0]`, `$this::y()`, `$this()`. Such an expression needs parentheses unless the
	 * grammar makes it a primary one.
	 */
	public function isDereferenced(): bool
	{
		return $this->getAccessKind() !== null;
	}


	/**
	 * Whether what is written after the expression may reach into it with no parentheses around it.
	 * A call takes a name or a member written before it for its own, which calls another thing entirely,
	 * and :: takes the name of a class, which a name written there would become.
	 */
	public function isDereferenceable(AccessKind $by = AccessKind::Member): bool
	{
		return $this instanceof VariableNode
			|| $this instanceof ArrayAccessNode
			|| $this instanceof FunctionCallNode
			|| $this instanceof MethodCallNode
			|| $this instanceof StaticMethodCallNode
			|| $this instanceof ParenthesizedNode
			|| ($by !== AccessKind::Call && (
				$this instanceof PropertyFetchNode
				|| $this instanceof StaticPropertyFetchNode
				|| $this instanceof ClassConstantFetchNode
			))
			|| ($by === AccessKind::Member && (
				$this instanceof ConstantFetchNode
				|| $this instanceof BooleanNode
				|| $this instanceof NullNode
			));
	}


	/** Whether the expression may stand where a class is named: a variable and what is read out of one. */
	public function canNameClass(): bool
	{
		return $this instanceof VariableNode
			|| $this instanceof PropertyFetchNode
			|| $this instanceof StaticPropertyFetchNode
			|| $this instanceof ArrayAccessNode
			|| $this instanceof ParenthesizedNode;
	}


	/**
	 * Whether the expression may stand where a place is assigned to: a variable, an element, a property,
	 * reached through a chain PHP writes through. A ?-> anywhere along it rules the write out, a call in the
	 * chain included, and so does a chain starting at a value of its own, a literal, a constant, `new` or
	 * `clone`, which has no place to write to. It says nothing about reading; that is isRepeatableRead(),
	 * and the two answer of different halves of `=`.
	 */
	public function isWritable(): bool
	{
		if (
			!$this instanceof VariableNode
			&& !$this instanceof ArrayAccessNode
			&& !$this instanceof StaticPropertyFetchNode
			&& !$this instanceof PropertyFetchNode
		) {
			return false;
		}

		$node = $this;
		while (($inner = $node->reachInto()) !== null) {
			if (($node instanceof PropertyFetchNode || $node instanceof MethodCallNode) && $node->isNullsafe()) {
				return false;
			}

			$node = $inner;
		}

		// the start of the chain, which a variable, a call or a static member of a named class can be
		return $node instanceof VariableNode
			|| $node instanceof FunctionCallNode
			|| $node instanceof StaticPropertyFetchNode
			|| $node instanceof StaticMethodCallNode;
	}


	/**
	 * The expression this one reads out of, which is the chain a write reaches through; null where it reads
	 * out of nothing, a function call, a named class and the index of an element among them.
	 */
	private function reachInto(): ?self
	{
		return match (true) {
			$this instanceof PropertyFetchNode, $this instanceof MethodCallNode => $this->object,
			$this instanceof ArrayAccessNode, $this instanceof ParenthesizedNode => $this->expression,
			$this instanceof StaticPropertyFetchNode, $this instanceof StaticMethodCallNode => $this->class instanceof self ? $this->class : null,
			default => null,
		};
	}


	/**
	 * Whether reading the expression again gives the same value with no side effects: variables, property,
	 * constant and offset fetches and literals, nothing that runs code of its own. The answer is syntactic,
	 * so what the language runs behind such a read is out of sight and does not count: a magic getter or
	 * a property hook behind a fetch, an ArrayAccess behind an offset, a __toString() behind a string that
	 * interpolates. Whoever cannot assume that much has to know the types, which a syntax tree does not.
	 */
	public function isRepeatableRead(): bool
	{
		foreach ([$this, ...$this->find(Node::class)] as $node) {
			// what is not an expression runs nothing of its own: lists, names, the pieces of a string
			if (
				$node instanceof self
				&& !$node instanceof VariableNode
				&& !$node instanceof ArrayAccessNode
				&& !$node instanceof PropertyFetchNode
				&& !$node instanceof StaticPropertyFetchNode
				&& !$node instanceof ClassConstantFetchNode
				&& !$node instanceof ConstantFetchNode
				&& !$node instanceof ScalarNode
			) {
				return false;
			}
		}

		return true;
	}


	/**
	 * The value the expression is written as: a scalar, null, true, false, or an array of them. A name
	 * standing for a constant is not one, its value being a matter of what the code around it defines.
	 * @throws \LogicException  where the expression is written as no value; hasValue() tells beforehand
	 */
	public function toValue(): mixed
	{
		$value = $this->readValue();
		return $value === null
			? throw new \LogicException('The expression has no value of its own: ' . $this->text)
			: $value[0];
	}


	/** Whether the expression is written as a value, which is what toValue() gives. */
	public function hasValue(): bool
	{
		return $this->readValue() !== null;
	}


	/** @return ?array{mixed}  the value in a list, so that null tells no value apart from the value null */
	private function readValue(): ?array
	{
		if (
			$this instanceof Scalar\IntegerNode
			|| $this instanceof Scalar\FloatNode
			|| $this instanceof Scalar\StringNode
			|| $this instanceof Scalar\UnquotedStringNode
		) {
			return [$this->value];

		} elseif ($this instanceof Scalar\HeredocNode) {
			return $this->hasInterpolation() ? null : [$this->value];

		} elseif ($this instanceof Expression\ParenthesizedNode) {
			return $this->expression->readValue();

		} elseif ($this instanceof Scalar\BooleanNode) {
			return [$this->value];

		} elseif ($this instanceof Scalar\NullNode) {
			return [null];

		} elseif ($this instanceof Expression\UnaryOpNode) {
			$value = $this->operator->is('-', '+') ? $this->expression->readValue() : null;
			return is_int($value[0] ?? null) || is_float($value[0] ?? null)
				? [$this->operator->is('-') ? -$value[0] : $value[0]]
				: null;

		} elseif ($this instanceof Expression\ArrayNode) {
			return self::readArrayValue($this);
		}

		return null;
	}


	/** @return ?array{array<array-key, mixed>} */
	private static function readArrayValue(Expression\ArrayNode $array): ?array
	{
		$result = [];
		foreach ($array->items as $item) {
			if (!$item instanceof ArrayItemNode || $item->ampersand) {
				return null;
			}

			// an item that is a destructuring target has no value
			$value = $item->value instanceof self ? $item->value->readValue() : null;
			if ($value === null) {
				return null;
			} elseif ($item->ellipsis) {
				if (!is_array($value[0])) {
					return null;
				}

				foreach ($value[0] as $key => $item) { // spreading renumbers what it brings, not what is there
					if (is_string($key)) {
						$result[$key] = $item;
					} else {
						$result[] = $item;
					}
				}

			} elseif ($item->key === null) {
				$result[] = $value[0];

			} else {
				$key = $item->key->readValue();
				if ($key === null || (!is_int($key[0]) && !is_string($key[0]))) {
					return null;
				}

				$result[$key[0]] = $value[0];
			}
		}

		return [$result];
	}
}
