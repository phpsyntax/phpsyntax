<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Helpers, Lexer, Token};
use PhpSyntax\Nodes\{ExpressionNode, OperatorNode};
use function in_array;


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


	/**
	 * Replaces the operator by another unary one, the trivia around it staying, and puts the operand and the
	 * operation itself in parentheses where the new precedence asks, `!` binding looser than the rest.
	 * @throws \InvalidArgumentException  for what is no unary operator
	 */
	public function replaceOperator(string $operator): void
	{
		if (!in_array($operator, ['+', '-', '!', '~', '@'], true)) {
			throw new \InvalidArgumentException(Helpers::formatCode($operator) . ' is not a unary operator.');
		} elseif ($operator === $this->operator->text) {
			return;
		}

		$this->operator->replaceWith(Lexer::readToken($operator) ?? throw new \LogicException);
		ParenthesizedNode::fit($this->expression);
		ParenthesizedNode::fit($this);
	}
}
