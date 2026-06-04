<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * One `#[...]` group of attributes.
 */
final class AttributeGroupNode extends Node
{
	public const Slots = ['openAttribute', 'attributes', 'closeBracket'];


	/** @internal */
	public function __construct(
		public Token $openAttribute { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<AttributeNode> */
		public SeparatedNodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeBracket { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
