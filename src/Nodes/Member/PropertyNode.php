<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\Nodes\{AttributeAwareNode, AttributeGroupNode, MemberNode, ModifiersNode, PlainNodeList, SeparatedNodeList, TypeNode};
use PhpSyntax\{Surgery, Token};


/**
 * Property declaration; one or more properties, optionally with hooks.
 */
final class PropertyNode extends MemberNode implements AttributeAwareNode
{
	public const Slots = ['attributes', 'modifiers', 'type', 'items', 'semicolon', 'openBrace', 'hooks', 'closeBrace'];

	/** @var PlainNodeList<AttributeGroupNode> */
	public PlainNodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ModifiersNode $modifiers { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?TypeNode $type = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<PropertyItemNode> */
	public SeparatedNodeList $items { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $semicolon = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $openBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var ?PlainNodeList<PropertyHookNode> */
	public ?PlainNodeList $hooks = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $closeBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param PlainNodeList<AttributeGroupNode> $attributes
	 * @param SeparatedNodeList<PropertyItemNode> $items
	 * @param ?PlainNodeList<PropertyHookNode> $hooks
	 */
	public function __construct(
		PlainNodeList $attributes,
		ModifiersNode $modifiers,
		?TypeNode $type,
		SeparatedNodeList $items,
		?Token $semicolon,
		?Token $openBrace,
		?PlainNodeList $hooks,
		?Token $closeBrace,
	) {
		$this->attributes = $attributes;
		$this->modifiers = $modifiers;
		$type === null || $this->type = $type;
		$this->items = $items;
		$semicolon === null || $this->semicolon = $semicolon;
		$openBrace === null || $this->openBrace = $openBrace;
		$hooks === null || $this->hooks = $hooks;
		$closeBrace === null || $this->closeBrace = $closeBrace;
	}


	/**
	 * Writes the type before the items with one space after it, or removes it with its space, its comments
	 * staying; a type standing in a tree comes as a copy without the trivia on its edges.
	 */
	public function setType(?TypeNode $type): static
	{
		Surgery::writeType($this, $type);
		return $this;
	}
}
