<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Type;

use PhpSyntax\Nodes\{SeparatedNodeList, TypeNode};


/**
 * Union type A|B; a member may be a parenthesized intersection (DNF).
 */
final class UnionTypeNode extends TypeNode
{
	public const Slots = ['types'];

	/** @var SeparatedNodeList<NamedTypeNode|IntersectionTypeNode> */
	public SeparatedNodeList $types { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param SeparatedNodeList<NamedTypeNode|IntersectionTypeNode> $types
	 */
	public function __construct(SeparatedNodeList $types)
	{
		$this->types = $types;
	}
}
