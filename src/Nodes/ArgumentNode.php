<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * Argument of a call: optionally named, by reference or unpacked.
 */
final class ArgumentNode extends Node
{
	public const Slots = ['name', 'colon', 'ampersand', 'ellipsis', 'value'];


	/** @internal */
	public function __construct(
		public ?IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $ampersand { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $ellipsis { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $value { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
