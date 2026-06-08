<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;


/**
 * Declaration with members: class, interface, trait, enum, anonymous class; an interface, because an
 * anonymous class stands inside a `new` expression and the rest are statements.
 */
interface ClassLikeNode
{
	public ?IdentifierNode $name { get; }

	/** @var NodeList<MemberNode> */
	public NodeList $members { get; }
}
