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
			$parent instanceof FunctionCallNode => AccessKind::Call,
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
}
