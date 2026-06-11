<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{DereferenceKind, Helpers, Node, Token};
use PhpSyntax\Nodes\Expression\{ArrayAccessNode, ArrayNode, AssignmentByReferenceNode, AssignmentNode, BinaryOpNode, CastNode, ClassConstantFetchNode, CombinedAssignmentNode, ConstantFetchNode, EmptyNode, FunctionCallNode, InstanceofNode, IssetNode, MethodCallNode, ParenthesizedNode, PostfixOpNode, PrefixOpNode, PropertyFetchNode, StaticMethodCallNode, StaticPropertyFetchNode, UnaryOpNode, VariableNode};
use PhpSyntax\Nodes\Scalar\{BooleanNode, NullNode};
use PhpSyntax\Nodes\Statement\{ForeachNode, GlobalNode, UnsetNode};
use function is_array, is_float, is_int, is_string;


/**
 * Expression, which stands for a value; a destructuring stands where a target is written and is a DestructuringNode,
 * not one of these.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
abstract class ExpressionNode extends Node
{
	/**
	 * How the parent reaches into this expression, null where it does not: `$this->x`, `$this->y()` and
	 * `$this[0]` fetch from it, `$this()` calls it, `$this::y()` takes it for the name of a class.
	 */
	public function getDereferenceKind(): ?DereferenceKind
	{
		$parent = $this->parent;
		return match (true) {
			$parent instanceof MethodCallNode, $parent instanceof PropertyFetchNode => $parent->object === $this ? DereferenceKind::Fetch : null,
			$parent instanceof ArrayAccessNode => $parent->expression === $this ? DereferenceKind::Fetch : null,
			$parent instanceof FunctionCallNode => DereferenceKind::Call,
			$parent instanceof StaticMethodCallNode, $parent instanceof StaticPropertyFetchNode, $parent instanceof ClassConstantFetchNode => $parent->class === $this ? DereferenceKind::StaticAccess : null,
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
		return $this->getDereferenceKind() !== null;
	}


	/**
	 * Whether what is written after the expression may reach into it with no parentheses around it.
	 * A call takes a name or a member written before it for its own, which calls another thing entirely,
	 * and `::` takes the name of a class, which a name written there would become.
	 */
	public function isDereferenceable(DereferenceKind $by = DereferenceKind::Fetch): bool
	{
		return $this instanceof VariableNode
			|| $this instanceof ArrayAccessNode
			|| $this instanceof FunctionCallNode
			|| $this instanceof MethodCallNode
			|| $this instanceof StaticMethodCallNode
			|| $this instanceof ParenthesizedNode
			|| ($by !== DereferenceKind::Call && (
				$this instanceof PropertyFetchNode
				|| $this instanceof StaticPropertyFetchNode
				|| $this instanceof ClassConstantFetchNode
			))
			|| ($by === DereferenceKind::Fetch && (
				$this instanceof ConstantFetchNode
				|| $this instanceof BooleanNode
				|| $this instanceof NullNode
			));
	}


	/**
	 * Whether the expression may stand where a class is named: a variable, a static property of a named class
	 * and what is read out of either all the way down, a property or an element, and an expression in
	 * parentheses; `new f()->b` instantiates f and `new A::B[0]` is no code.
	 * @internal what `ParenthesizedNode::isRedundant()` asks
	 */
	public function canNameClass(): bool
	{
		return match (true) {
			$this instanceof VariableNode, $this instanceof ParenthesizedNode => true,
			$this instanceof PropertyFetchNode => self::isReadOutOfVariable($this->object),
			$this instanceof ArrayAccessNode => self::isReadOutOfVariable($this->expression),
			$this instanceof StaticPropertyFetchNode => $this->class instanceof NameNode || self::isReadOutOfVariable($this->class),
			default => false,
		};
	}


	private static function isReadOutOfVariable(self $expression): bool
	{
		return !$expression instanceof ParenthesizedNode && $expression->canNameClass();
	}


	/**
	 * Whether the expression may stand where a place is assigned to: a variable, an element, a property,
	 * reached through a chain PHP writes through. A `?->` anywhere along it rules the write out, a call in the
	 * chain included, and so does a chain starting at a value of its own, a literal, a constant, `new` or
	 * `clone`, which has no place to write to. It says nothing about reading; that is `isRepeatableRead()`,
	 * and the two answer for the two sides of `=`.
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
			if (($node instanceof PropertyFetchNode || $node instanceof MethodCallNode) && $node->nullsafe) {
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
	 * Whether something writes the expression or an element of it, a variable, a property or an element alike: it is
	 * assigned, stepped, unset, bound, or taken by reference, either side of `=&` among them, the right one becoming
	 * a reference a write may later go through.
	 */
	public function isWritten(): bool
	{
		$node = $this;
		$parent = $node->parent;
		while ( // destructuring writes every value inside it, not a key, and unset and global take theirs in a list
			($parent instanceof ArrayAccessNode && $parent->expression === $node)
			|| $parent instanceof ParenthesizedNode
			|| $parent instanceof ArrayNode
			|| $parent instanceof DestructuringNode
			|| ($parent instanceof ArrayItemNode && $parent->value === $node)
			|| $parent instanceof SeparatedNodeList
		) {
			if ($parent instanceof ArrayItemNode && $parent->ampersand !== null) {
				return true; // [&$a] takes it by reference
			}

			[$node, $parent] = [$parent, $parent->parent];
		}

		return match (true) {
			$parent instanceof AssignmentNode,
			$parent instanceof CombinedAssignmentNode => $parent->findSlotOf($node) === 'target',
			$parent instanceof AssignmentByReferenceNode,
			$parent instanceof PrefixOpNode,
			$parent instanceof PostfixOpNode => true,
			$parent instanceof ArgumentNode, $parent instanceof ClosureUseNode => $parent->ampersand !== null,
			$parent instanceof ForeachNode => $parent->findSlotOf($node) === 'key' || $parent->findSlotOf($node) === 'value',
			$parent instanceof UnsetNode,
			$parent instanceof GlobalNode => true,
			$parent instanceof StaticVariableNode, $parent instanceof CatchNode => $parent->variable === $node,
			default => false,
		};
	}


	/**
	 * Whether reading the expression again gives the same value with no side effects: variables, property,
	 * constant and offset fetches, literals, arrays of such items and what parentheses or a unary operator make
	 * of them, nothing that runs code of its own; an unpacked item may run a generator and one by reference
	 * creates the variable, so neither is repeatable. The answer is syntactic, so what the language runs behind
	 * such a read is out of sight and does not count: a magic getter or a property hook behind a fetch, an
	 * `ArrayAccess` behind an offset, a `__toString()` behind a string that interpolates. Whoever cannot assume that
	 * much has to know the types, which a syntax tree does not.
	 */
	public function isRepeatableRead(): bool
	{
		foreach ([$this, ...$this->find(Node::class)] as $node) {
			// what is not an expression runs nothing of its own: lists, names, the pieces of a string
			if (
				($node instanceof ArrayItemNode && ($node->ellipsis !== null || $node->ampersand !== null))
				|| (
					$node instanceof self
					&& !$node instanceof VariableNode
					&& !$node instanceof ArrayAccessNode
					&& !$node instanceof PropertyFetchNode
					&& !$node instanceof StaticPropertyFetchNode
					&& !$node instanceof ClassConstantFetchNode
					&& !$node instanceof ConstantFetchNode
					&& !$node instanceof ScalarNode
					&& !$node instanceof ArrayNode
					&& !$node instanceof ParenthesizedNode
					&& !$node instanceof UnaryOpNode
				)
			) {
				return false;
			}
		}

		return true;
	}


	/**
	 * Whether the expression yields a boolean whatever its operands: a comparison, a logical operation, a negation,
	 * `instanceof`, `isset()`, `empty()`, a bool cast or a boolean literal.
	 */
	public function evaluatesToBoolean(): bool
	{
		return match (true) {
			$this instanceof BinaryOpNode => $this->isLogical() || $this->operator->is([
				Token::IsEqual, Token::IsNotEqual, Token::IsIdentical, Token::IsNotIdentical,
				'<', '>', Token::IsSmallerOrEqual, Token::IsGreaterOrEqual,
			]),
			$this instanceof UnaryOpNode => $this->operator->is('!'),
			$this instanceof CastNode => $this->operator->is(Token::BoolCast),
			$this instanceof ParenthesizedNode => $this->expression->evaluatesToBoolean(),
			default => $this instanceof BooleanNode || $this instanceof InstanceofNode || $this instanceof IssetNode || $this instanceof EmptyNode,
		};
	}


	/**
	 * The value the expression is written as: a scalar, `null`, `true`, `false`, or an array of them. A name
	 * standing for a constant is not one, its value being a matter of what the code around it defines.
	 * @throws \LogicException  where the expression is written as no value; `hasValue()` tells beforehand
	 */
	public function toValue(): mixed
	{
		$value = $this->readValue();
		return $value === null
			? throw new \LogicException('Expression ' . Helpers::formatCode($this->text) . ' has no value of its own.')
			: $value[0];
	}


	/** Whether the expression is written as a value, which is what `toValue()` gives. */
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
			|| $this instanceof Scalar\BooleanNode
		) {
			return [$this->value];

		} elseif ($this instanceof Scalar\HeredocNode) {
			return $this->hasInterpolation() ? null : [$this->value];

		} elseif ($this instanceof Expression\ParenthesizedNode) {
			return $this->expression->readValue();

		} elseif ($this instanceof Scalar\NullNode) {
			return [null];

		} elseif ($this instanceof Expression\UnaryOpNode) {
			$value = $this->operator->is(['-', '+']) ? $this->expression->readValue() : null;
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

				foreach ($value[0] as $key => $element) { // spreading renumbers what it brings, not what is there
					if (is_string($key)) {
						$result[$key] = $element;
					} else {
						$result[] = $element;
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
