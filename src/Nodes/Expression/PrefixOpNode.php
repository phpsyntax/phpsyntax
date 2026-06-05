<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Token};
use PhpSyntax\Nodes\{ExpressionNode, OperatorNode};


/**
 * Prefix increment or decrement.
 */
final class PrefixOpNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['operator', 'target'];

	public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $target { set => $this->prepareSlot(__PROPERTY__, $value); }

	public int $precedence { get => 240; }
	public Associativity $associativity { get => Associativity::Right; }


	/** @internal */
	public function __construct(Token $operator, ExpressionNode $target)
	{
		$this->operator = $operator;
		$this->target = $target;
	}
}
