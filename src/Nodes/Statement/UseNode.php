<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{NameNode, SeparatedNodeList, StatementNode, UseItemNode};
use PhpSyntax\{SymbolKind, Token};


/**
 * `use` import of classes, functions or constants, written item by item or as a group under a prefix
 * (`use A\{B, C};`), which fills the slots the other form leaves empty.
 */
final class UseNode extends StatementNode
{
	public const Slots = ['useKeyword', 'kindKeyword', 'prefix', 'backslash', 'openBrace', 'items', 'closeBrace', 'semicolon'];

	public Token $useKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $kindKeyword = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?NameNode $prefix = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $backslash = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $openBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<UseItemNode> */
	public SeparatedNodeList $items { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $closeBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** What the statement imports, which every item without a type of its own imports too. */
	public SymbolKind $symbolKind {
		get => SymbolKind::fromKeyword($this->kindKeyword);
	}


	/**
	 * @internal
	 * @param SeparatedNodeList<UseItemNode> $items
	 */
	public function __construct(
		Token $useKeyword,
		?Token $kindKeyword,
		?NameNode $prefix,
		?Token $backslash,
		?Token $openBrace,
		SeparatedNodeList $items,
		?Token $closeBrace,
		Token $semicolon,
	) {
		$this->useKeyword = $useKeyword;
		$kindKeyword === null || $this->kindKeyword = $kindKeyword;
		$prefix === null || $this->prefix = $prefix;
		$backslash === null || $this->backslash = $backslash;
		$openBrace === null || $this->openBrace = $openBrace;
		$this->items = $items;
		$closeBrace === null || $this->closeBrace = $closeBrace;
		$this->semicolon = $semicolon;
	}


	/**
	 * Whether the items are written as a group under a prefix, which every one of them imports.
	 * @phpstan-assert-if-true !null $this->prefix
	 */
	public function isGroup(): bool
	{
		return $this->prefix !== null;
	}
}
