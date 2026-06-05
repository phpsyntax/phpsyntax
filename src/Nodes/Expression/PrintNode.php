<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ExpressionNode, RightExtendingNode};
use PhpSyntax\Token;


/**
 * `print` expression.
 */
final class PrintNode extends ExpressionNode implements RightExtendingNode
{
	public const Slots = ['printKeyword', 'expression'];


	/** @internal */
	public function __construct(
		public Token $printKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	public function getPrecedence(): array
	{
		return [60, self::RightAssociative];
	}
}
