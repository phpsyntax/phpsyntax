<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Token};
use PhpSyntax\Nodes\{ExpressionNode, RightExtendingNode};


/**
 * `yield from` expression.
 */
final class YieldFromNode extends ExpressionNode implements RightExtendingNode
{
	public const Slots = ['yieldFromKeyword', 'expression'];

	public Token $yieldFromKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); }

	public int $precedence { get => 75; }
	public Associativity $associativity { get => Associativity::Right; }


	/** @internal */
	public function __construct(Token $yieldFromKeyword, ExpressionNode $expression)
	{
		$this->yieldFromKeyword = $yieldFromKeyword;
		$this->expression = $expression;
	}
}
