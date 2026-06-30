<?php declare(strict_types=1);

namespace PhpSyntax;


/**
 * Pre-order walk over nodes and tokens with enter and leave callbacks; every node entered is left again,
 * so a node a callback gets may be one already taken out of the tree, which its parent says. A callback
 * may replace or remove the node it received: the walk then does not descend into it, skips siblings
 * detached meanwhile and leaves inserted ones for the next walk. It may also return DontTraverseChildren
 * to keep the walk out of a subtree, or StopTraversal to end it: no node is entered after that, and the
 * ones already entered are left as the walk unwinds, the node that stopped it among them.
 */
final class Traverser
{
	public const DontTraverseChildren = 1;
	public const StopTraversal = 2;

	/** @var ?\Closure((Node | Token)): mixed */
	private ?\Closure $enter = null;

	/** @var ?\Closure((Node | Token)): mixed */
	private ?\Closure $leave = null;

	private bool $stopped = false;


	/**
	 * Walks the tree; a callback returns DontTraverseChildren or StopTraversal to steer the walk, anything
	 * else (a value of its own, or nothing) leaves it alone.
	 * @param ?callable(Node|Token): mixed  $enter
	 * @param ?callable(Node|Token): mixed  $leave
	 */
	public function traverse(Node $root, ?callable $enter = null, ?callable $leave = null): void
	{
		$this->enter = $enter === null ? null : $enter(...);
		$this->leave = $leave === null ? null : $leave(...);
		$this->stopped = false;
		$this->visit($root, $root->parent);
	}


	private function visit(Node|Token $node, ?Node $parent): void
	{
		$result = $this->enter?->__invoke($node);
		if ($result === self::StopTraversal) {
			$this->stopped = true;
		}

		if (
			!$this->stopped
			&& $node instanceof Node
			&& $result !== self::DontTraverseChildren
			&& $node->parent === $parent // a replaced node is not descended into
		) {
			foreach ($node->getChildren() as $child) { // a snapshot: the callbacks may change the children
				if ($child->parent === $node) {
					$this->visit($child, $node);
				}

				if ($this->stopped) {
					break;
				}
			}
		}

		if ($this->leave?->__invoke($node) === self::StopTraversal) {
			$this->stopped = true;
		}
	}
}
