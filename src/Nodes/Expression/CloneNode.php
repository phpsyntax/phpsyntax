<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Token};
use PhpSyntax\Nodes\{ExpressionNode, OperatorNode};


/**
 * `clone` expression; `clone(...)` with arguments is a function call.
 */
final class CloneNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['cloneKeyword', 'expression'];

	public Token $cloneKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); }

	public int $precedence { get => 270; }
	public Associativity $associativity { get => Associativity::None; }


	/** @internal */
	public function __construct(Token $cloneKeyword, ExpressionNode $expression)
	{
		$this->cloneKeyword = $cloneKeyword;
		$this->expression = $expression;
	}
}
