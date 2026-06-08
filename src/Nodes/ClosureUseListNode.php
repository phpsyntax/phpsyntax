<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * The `use (...)` clause of a closure.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class ClosureUseListNode extends Node
{
	public const Slots = ['useKeyword', 'openParen', 'items', 'closeParen'];

	public Token $useKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<ClosureUseNode> */
	public SeparatedNodeList $items { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param SeparatedNodeList<ClosureUseNode> $items
	 */
	public function __construct(Token $useKeyword, Token $openParen, SeparatedNodeList $items, Token $closeParen)
	{
		$this->useKeyword = $useKeyword;
		$this->openParen = $openParen;
		$this->items = $items;
		$this->closeParen = $closeParen;
	}
}
