<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\Nodes\{IdentifierNode, NameNode, SeparatedNodeList};
use PhpSyntax\Token;


/**
 * Trait adaptation `A::m insteadof B`.
 */
final class TraitPrecedenceNode extends TraitAdaptationNode
{
	public const Slots = ['trait', 'doubleColon', 'method', 'insteadofKeyword', 'traits', 'semicolon'];


	/** @internal */
	public function __construct(
		public NameNode $trait { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $doubleColon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public IdentifierNode $method { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $insteadofKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<NameNode> */
		public SeparatedNodeList $traits { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
