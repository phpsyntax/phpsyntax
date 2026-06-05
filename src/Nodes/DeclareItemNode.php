<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * Directive of a `declare` statement: `strict_types=1`.
 */
final class DeclareItemNode extends Node
{
	public const Slots = ['name', 'equals', 'value'];


	/** @internal */
	public function __construct(
		public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $equals { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $value { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
