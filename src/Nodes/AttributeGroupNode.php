<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * One `#[...]` group of attributes.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class AttributeGroupNode extends Node
{
	public const Slots = ['openBracket', 'items', 'closeBracket'];

	public Token $openBracket { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<AttributeNode> */
	public SeparatedNodeList $items { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeBracket { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param SeparatedNodeList<AttributeNode> $items
	 */
	public function __construct(Token $openBracket, SeparatedNodeList $items, Token $closeBracket)
	{
		$this->openBracket = $openBracket;
		$this->items = $items;
		$this->closeBracket = $closeBracket;
	}
}
