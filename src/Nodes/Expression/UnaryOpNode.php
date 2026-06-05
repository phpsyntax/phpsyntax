<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Token};
use PhpSyntax\Nodes\{ExpressionNode, OperatorNode};


/**
 * Unary operation written before its operand: `+`, `-`, `!`, `~`, `@`; `++` and `--` are a PrefixOpNode, the way the grammar tells them apart.
 */
final class UnaryOpNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['operator', 'expression'];

	public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); }

	public int $precedence { get => $this->operator->is('!') ? 220 : 240; }
	public Associativity $associativity { get => Associativity::Right; }


	/** @internal */
	public function __construct(Token $operator, ExpressionNode $expression)
	{
		$this->operator = $operator;
		$this->expression = $expression;
	}
}
