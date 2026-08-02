<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;


/**
 * Expression written with an operator, which is what decides whether parentheses around it are needed.
 * An arrow function is one of them: its body reaches as far as the code lets it.
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
