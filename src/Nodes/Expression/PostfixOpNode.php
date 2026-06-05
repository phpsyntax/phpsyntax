<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Token};
use PhpSyntax\Nodes\{ExpressionNode, OperatorNode};


/**
 * Postfix increment or decrement.
 */
final class PostfixOpNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['target', 'operator'];

	public ExpressionNode $target { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); }

	public int $precedence { get => 240; }
	public Associativity $associativity { get => Associativity::Left; }


	/** @internal */
	public function __construct(ExpressionNode $target, Token $operator)
	{
		$this->target = $target;
		$this->operator = $operator;
	}
}
