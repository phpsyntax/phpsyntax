<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\Nodes\{MemberNode, NameNode, NodeList, SeparatedNodeList};
use PhpSyntax\Token;


/**
 * `use` of traits with optional adaptations in braces.
 */
final class TraitUseNode extends MemberNode
{
	public const Slots = ['useKeyword', 'traits', 'semicolon', 'openBrace', 'adaptations', 'closeBrace'];


	/** @internal */
	public function __construct(
		public Token $useKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<NameNode> */
		public SeparatedNodeList $traits { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var ?NodeList<TraitAdaptationNode> */
		public ?NodeList $adaptations { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
