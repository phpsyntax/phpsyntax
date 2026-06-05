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
	public const Slots = ['useKeyword', 'type', 'prefix', 'namespaceSeparator', 'openBrace', 'items', 'closeBrace', 'semicolon'];

	/** What the statement imports, which every item without a type of its own imports too. */
	public SymbolKind $kind {
		get => SymbolKind::fromUseType($this->type);
	}


	/** @internal */
	public function __construct(
		public Token $useKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $type { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?NameNode $prefix { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $namespaceSeparator { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<UseItemNode> */
		public SeparatedNodeList $items { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
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
