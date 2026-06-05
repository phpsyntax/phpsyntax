<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Token};
use PhpSyntax\Nodes\{ExpressionNode, OperatorNode};


/**
 * Combined assignment `$a += $b`, whose operator token tells which operation is baked into it.
 */
final class CombinedAssignmentNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['target', 'operator', 'expression'];

	public ExpressionNode $target { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); }

	public int $precedence { get => 90; }
	public Associativity $associativity { get => Associativity::Right; }


	/** @internal */
	public function __construct(ExpressionNode $target, Token $operator, ExpressionNode $expression)
	{
		$this->target = $target;
		$this->operator = $operator;
		$this->expression = $expression;
	}
}
