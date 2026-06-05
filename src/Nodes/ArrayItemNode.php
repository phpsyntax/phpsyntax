<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\Expression\ListNode;


/**
 * Item of an array literal or a destructuring list: value with optional key, by reference or unpacked.
 */
final class ArrayItemNode extends Node
{
	public const Slots = ['key', 'doubleArrow', 'ampersand', 'ellipsis', 'value'];


	/** @internal */
	public function __construct(
		public ?ExpressionNode $key { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $doubleArrow { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $ampersand { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $ellipsis { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode|ListNode $value { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
