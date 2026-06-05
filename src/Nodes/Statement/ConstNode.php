<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{AttributeAwareNode, AttributeGroupNode, ConstItemNode, PlainNodeList, SeparatedNodeList, StatementNode};
use PhpSyntax\Token;


/**
 * `const` statement outside a class.
 */
final class ConstNode extends StatementNode implements AttributeAwareNode
{
	public const Slots = ['attributes', 'constKeyword', 'items', 'semicolon'];

	/** @var PlainNodeList<AttributeGroupNode> */
	public PlainNodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $constKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<ConstItemNode> */
	public SeparatedNodeList $items { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param PlainNodeList<AttributeGroupNode> $attributes
	 * @param SeparatedNodeList<ConstItemNode> $items
	 */
	public function __construct(PlainNodeList $attributes, Token $constKeyword, SeparatedNodeList $items, Token $semicolon)
	{
		$this->attributes = $attributes;
		$this->constKeyword = $constKeyword;
		$this->items = $items;
		$this->semicolon = $semicolon;
	}
}
