<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Token};
use PhpSyntax\Nodes\{ExpressionNode, RightExtendingNode};


/**
 * `print` expression.
 */
final class PrintNode extends ExpressionNode implements RightExtendingNode
{
	public const Slots = ['printKeyword', 'expression'];

	public Token $printKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); }

	public int $precedence { get => 60; }
	public Associativity $associativity { get => Associativity::Right; }


	/** @internal */
	public function __construct(Token $printKeyword, ExpressionNode $expression)
	{
		$this->printKeyword = $printKeyword;
		$this->expression = $expression;
	}
}
