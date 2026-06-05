<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ExpressionNode, OperatorNode};
use PhpSyntax\Token;


/**
 * `clone` expression; `clone(...)` with arguments is a function call.
 */
final class CloneNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['cloneKeyword', 'expression'];


	/** @internal */
	public function __construct(
		public Token $cloneKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	public function getPrecedence(): array
	{
		return [270, self::NonAssociative];
	}
}
