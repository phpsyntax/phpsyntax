<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Type;

use PhpSyntax\Nodes\{SeparatedNodeList, TypeNode};
use PhpSyntax\Token;


/**
 * Intersection type A&B, parenthesized inside a union.
 */
final class IntersectionTypeNode extends TypeNode
{
	public const Slots = ['openParen', 'types', 'closeParen'];

	public ?Token $openParen = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<NamedTypeNode> */
	public SeparatedNodeList $types { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $closeParen = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param SeparatedNodeList<NamedTypeNode> $types
	 */
	public function __construct(?Token $openParen, SeparatedNodeList $types, ?Token $closeParen)
	{
		$openParen === null || $this->openParen = $openParen;
		$this->types = $types;
		$closeParen === null || $this->closeParen = $closeParen;
	}
}
