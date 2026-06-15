<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;


/**
 * The construct a line-opening token continues and what the token is to it, as `Indentation::findOwner()` finds it.
 */
final readonly class LineOwner
{
	public function __construct(
		/** the lowest ancestor the token does not begin */
		public Node $node,
		/** the child of the node the token stands in: a slot value, or a list for an item and for a separator alike */
		public Node|Token $child,
		/** the layout role of the slot the child fills */
		public LayoutRole $role,
		/** what the token begins: the child itself, or in a list the item holding the token, or the first item for a separator */
		public Node|Token $item,
	) {
	}
}
