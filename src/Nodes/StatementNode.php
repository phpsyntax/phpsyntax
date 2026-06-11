<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\Expression\{ExitNode, ThrowNode};
use PhpSyntax\Nodes\Statement\{BreakNode, ContinueNode, ExpressionStatementNode, GotoNode, ReturnNode};


/**
 * Statement: what a file, a block and the body of a control structure consist of.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
abstract class StatementNode extends Node
{
	/**
	 * Whether the code does not go on after the statement: `return`, `break`, `continue`, `goto`, `throw` or `exit`.
	 * It reads the statement itself, so an `if` whose every branch returns is no such statement; that is control flow.
	 */
	public function interruptsFlow(): bool
	{
		return match (true) {
			$this instanceof ReturnNode,
			$this instanceof BreakNode,
			$this instanceof ContinueNode,
			$this instanceof GotoNode => true,
			$this instanceof ExpressionStatementNode => $this->expression instanceof ThrowNode || $this->expression instanceof ExitNode,
			default => false,
		};
	}
}
