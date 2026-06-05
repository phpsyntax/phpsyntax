<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Token};
use PhpSyntax\Nodes\{DestructuringNode, ExpressionNode, OperatorNode};


/**
 * Assignment `$a = $b`.
 */
final class AssignmentNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['target', 'equals', 'expression'];

	public ExpressionNode|DestructuringNode $target { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $equals { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); }

	public int $precedence { get => 90; }
	public Associativity $associativity { get => Associativity::Right; }


	/** @internal */
	public function __construct(ExpressionNode|DestructuringNode $target, Token $equals, ExpressionNode $expression)
	{
		$this->target = $target;
		$this->equals = $equals;
		$this->expression = $expression;
	}
}
