<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\Expression\VariableNode;


/**
 * Variable of a `static` statement with an optional initializer.
 */
final class StaticVariableNode extends Node
{
	public const Slots = ['variable', 'equals', 'default'];


	/** @internal */
	public function __construct(
		public VariableNode $variable { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $equals { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ExpressionNode $default { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
