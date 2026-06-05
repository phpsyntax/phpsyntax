<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\Associativity;


/**
 * Expression written with an operator, which is what decides whether parentheses around it are needed.
 * Those whose right operand reaches as far as the code lets it, an arrow function among them, are
 * a RightExtendingNode.
 */
interface OperatorNode
{
	/**
	 * How tightly the operator binds, the higher the tighter, in the order the precedence declarations of
	 * the grammar put the operators; only the order of the numbers is meant, not their values.
	 */
	public int $precedence { get; }

	/** Which side the operator leans to, where an operand of the same precedence may stand. */
	public Associativity $associativity { get; }
}
