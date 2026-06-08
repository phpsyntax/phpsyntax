<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\Nodes\{AttributeGroupNode, ConstItemNode, MemberNode, ModifiersNode, NodeList, SeparatedNodeList, TypeNode};
use PhpSyntax\Token;


/**
 * Class constant declaration, optionally typed.
 */
final class ClassConstNode extends MemberNode
{
	public const Slots = ['attributes', 'modifiers', 'constKeyword', 'type', 'items', 'semicolon'];


	/** @internal */
	public function __construct(
		/** @var NodeList<AttributeGroupNode> */
		public NodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ModifiersNode $modifiers { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $constKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?TypeNode $type { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ConstItemNode> */
		public SeparatedNodeList $items { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
