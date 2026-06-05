<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ExpressionNode, OperatorNode};
use PhpSyntax\Token;


/**
 * Combined assignment `$a += $b`, whose operator token tells which operation is baked into it.
 */
final class CombinedAssignmentNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['target', 'operator', 'expression'];


	/** @internal */
	public function __construct(
		public ExpressionNode $target { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	public function getPrecedence(): array
	{
		return [90, self::RightAssociative];
	}
}
