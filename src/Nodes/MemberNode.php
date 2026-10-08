<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Nodes\Statement\{ClassNode, EnumNode};


/**
 * Member of a class-like declaration: property, constant, method, trait use, enum case.
 */
abstract class MemberNode extends Node
{
	/** Whether a descendant of the class-like the member stands in may exist: it is no final class, no enum and no anonymous class. */
	protected function isInExtendableClass(): bool
	{
		$class = $this->findAncestor(ClassLikeNode::class);
		return !$class instanceof AnonymousClassNode
			&& !$class instanceof EnumNode
			&& !($class instanceof ClassNode && $class->modifiers->final);
	}
}
