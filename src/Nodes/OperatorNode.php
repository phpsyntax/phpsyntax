<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;


/**
 * Expression written with an operator, which is what decides whether parentheses around it are needed.
 * Those whose right operand reaches as far as the code lets it, an arrow function among them, are
 * a RightExtendingNode.
 */
interface OperatorNode
{
	/** which side the operator leans to, where an operand of the same precedence may stand */
	public const LeftAssociative = -1, NonAssociative = 0, RightAssociative = 1;

	/**
	 * How tightly the operator binds and which side it leans to; the higher the number the tighter,
	 * in the order the precedence declarations of the grammar put the operators.
	 * @return array{int, self::LeftAssociative|self::NonAssociative|self::RightAssociative}
	 */
	public function getPrecedence(): array;
}
