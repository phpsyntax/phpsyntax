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


	/** @internal */
	public function __construct(
		public ?Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<TypeNode> */
		public SeparatedNodeList $types { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
