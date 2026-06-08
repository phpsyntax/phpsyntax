<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\Nodes\{AttributeAwareNode, AttributeGroupNode, ConstItemNode, MemberNode, ModifiersNode, PlainNodeList, SeparatedNodeList, TypeNode};
use PhpSyntax\Token;


/**
 * Class constant declaration, optionally typed.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class ClassConstNode extends MemberNode implements AttributeAwareNode
{
	public const Slots = ['attributes', 'modifiers', 'constKeyword', 'type', 'items', 'semicolon'];

	/** @var PlainNodeList<AttributeGroupNode> */
	public PlainNodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ModifiersNode $modifiers { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $constKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?TypeNode $type = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<ConstItemNode> */
	public SeparatedNodeList $items { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param PlainNodeList<AttributeGroupNode> $attributes
	 * @param SeparatedNodeList<ConstItemNode> $items
	 */
	public function __construct(
		PlainNodeList $attributes,
		ModifiersNode $modifiers,
		Token $constKeyword,
		?TypeNode $type,
		SeparatedNodeList $items,
		Token $semicolon,
	) {
		$this->attributes = $attributes;
		$this->modifiers = $modifiers;
		$this->constKeyword = $constKeyword;
		$type === null || $this->type = $type;
		$this->items = $items;
		$this->semicolon = $semicolon;
	}
}
