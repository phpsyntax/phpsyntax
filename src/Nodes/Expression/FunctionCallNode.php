<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ArgumentListNode, ExpressionNode, NameNode};


/**
 * Function call by name or on an expression.
 */
final class FunctionCallNode extends ExpressionNode
{
	public const Slots = ['name', 'arguments'];


	/** @internal */
	public function __construct(
		public NameNode|ExpressionNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ArgumentListNode $arguments { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
