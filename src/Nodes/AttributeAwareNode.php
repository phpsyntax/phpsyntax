<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;


/**
 * Declaration that may carry attributes, its list empty where none is written: a class-like or a function-like one,
 * a member, a constant, an enum case, a parameter; an interface, because they share no base class.
 */
interface AttributeAwareNode
{
	/** @var PlainNodeList<AttributeGroupNode> */
	public PlainNodeList $attributes { get; }
}
