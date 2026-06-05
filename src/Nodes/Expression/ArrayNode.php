<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ArrayItemNode, EmptyArrayItemNode, ExpressionNode, SeparatedNodeList};
use PhpSyntax\Token;


/**
 * Array literal in either syntax: `[...]` or `array(...)`. A short array standing where a place is assigned
 * to destructures instead, and is a ListNode.
 */
final class ArrayNode extends ExpressionNode
{
	public const Slots = ['arrayKeyword', 'openDelimiter', 'items', 'closeDelimiter'];


	/** @internal */
	public function __construct(
		public ?Token $arrayKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openDelimiter { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ArrayItemNode|EmptyArrayItemNode> */
		public SeparatedNodeList $items { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeDelimiter { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
