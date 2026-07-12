<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Nodes\Expression\ArrayAccessNode;
use PhpSyntax\Nodes\Expression\ClassConstantFetchNode;
use PhpSyntax\Nodes\Expression\ConstantFetchNode;
use PhpSyntax\Nodes\Expression\PropertyFetchNode;
use PhpSyntax\Nodes\Expression\StaticPropertyFetchNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use function is_array, is_float, is_int, is_string;


abstract class ExpressionNode extends Node
{
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

			$value = $item->value->readValue();
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
