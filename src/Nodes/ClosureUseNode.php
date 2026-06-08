<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\Expression\VariableNode;


/**
 * Variable captured by a closure, optionally by reference.
 */
final class ClosureUseNode extends Node
{
	public const Slots = ['ampersand', 'variable'];


	/** @internal */
	public function __construct(
		public ?Token $ampersand { set => $this->prepareSlot(__PROPERTY__, $value); },
		public VariableNode $variable { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
