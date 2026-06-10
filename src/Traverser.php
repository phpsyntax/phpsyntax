<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;


/**
 * Pre-order walk over nodes and tokens with enter and leave callbacks; every node entered is left again,
 * so a node a callback gets may be one already taken out of the tree, which its parent says. A callback
 * may replace or remove the node it received: the walk then does not descend into it, skips siblings
 * detached meanwhile and leaves inserted ones for the next walk.
 */
final class Traverser
{
	private bool $stopped = false;


	private function __construct(
		/** @var ?\Closure((Node | Token)): mixed */
		private readonly ?\Closure $enter,
		/** @var ?\Closure((Node | Token)): mixed */
		private readonly ?\Closure $leave,
	) {
	}


	/**
	 * Walks the tree; a callback returns `TraverseAction::SkipChildren` to keep the walk out of a subtree, or
	 * `TraverseAction::Stop` to end it: no node is entered after that, and the ones already entered are left as
	 * the walk unwinds, the node that stopped it among them. Anything else (a value of its own, or nothing)
	 * leaves the walk alone. A callback may start a walk of its own.
	 * @param ?callable(Node|Token): mixed  $enter
	 * @param ?callable(Node|Token): mixed  $leave
	 */
	public static function traverse(Node $root, ?callable $enter = null, ?callable $leave = null): void
	{
		// an instance per walk: the recursion reads properties faster than parameters it passes down
		$walk = new self($enter === null ? null : $enter(...), $leave === null ? null : $leave(...));
		$walk->visit($root, $root->parent);
	}


	private function visit(Node|Token $node, ?Node $parent): void
	{
		$result = $this->enter?->__invoke($node);
		if ($result === TraverseAction::Stop) {
			$this->stopped = true;
		}

		if (
			!$this->stopped
			&& $node instanceof Node
			&& $result !== TraverseAction::SkipChildren
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

		if ($this->leave?->__invoke($node) === TraverseAction::Stop) {
			$this->stopped = true;
		}
	}
}
