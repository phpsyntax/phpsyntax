<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ExpressionNode, RightExtendingNode};
use PhpSyntax\Token;


/**
 * `include`, `include_once`, `require` or `require_once`; the keyword token tells which.
 */
final class IncludeNode extends ExpressionNode implements RightExtendingNode
{
	public const Slots = ['includeKeyword', 'expression'];


	/** @internal */
	public function __construct(
		public Token $includeKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	public function getPrecedence(): array
	{
		return [20, self::LeftAssociative];
	}
}
