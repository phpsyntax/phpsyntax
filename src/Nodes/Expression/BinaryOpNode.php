<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Helpers, Lexer, Token};
use PhpSyntax\Nodes\{ExpressionNode, OperatorNode};


/**
 * Binary operation; the operator token tells which (arithmetic, comparison, logical, bitwise, concatenation, coalesce, pipe).
 */
final class BinaryOpNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['left', 'operator', 'right'];

	public ExpressionNode $left { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $right { set => $this->prepareSlot(__PROPERTY__, $value); }

	public int $precedence { get => self::resolvePrecedence($this->operator->text)[0]; }
	public Associativity $associativity { get => self::resolvePrecedence($this->operator->text)[1]; }


	/** @internal */
	public function __construct(ExpressionNode $left, Token $operator, ExpressionNode $right)
	{
		$this->left = $left;
		$this->operator = $operator;
		$this->right = $right;
	}


	/**
	 * Replaces the operator by another binary one, the trivia around it staying, and puts the operands and the
	 * operation itself in parentheses where the new precedence asks, taking away those it makes needless.
	 * @throws \InvalidArgumentException  for what is no binary operator
	 */
	public function replaceOperator(string $operator): void
	{
		self::resolvePrecedence($operator);
		if ($operator === $this->operator->text) {
			return;
		}

		$this->operator->replaceWith(Lexer::readToken($operator) ?? throw new \LogicException);
		ParenthesizedNode::fit($this->left);
		ParenthesizedNode::fit($this->right);
		ParenthesizedNode::fit($this);
	}


	/** @return array{int, Associativity} */
	private static function resolvePrecedence(string $operator): array
	{
		return match (strtolower($operator)) {
			'**' => [250, Associativity::Right],
			'*', '/', '%' => [210, Associativity::Left],
			'+', '-' => [200, Associativity::Left],
			'<<', '>>' => [190, Associativity::Left],
			'.' => [185, Associativity::Left],
			'|>' => [183, Associativity::Left],
			'<', '<=', '>', '>=' => [180, Associativity::None],
			'==', '!=', '<>', '===', '!==', '<=>' => [170, Associativity::None],
			'&' => [160, Associativity::Left],
			'^' => [150, Associativity::Left],
			'|' => [140, Associativity::Left],
			'&&' => [130, Associativity::Left],
			'||' => [120, Associativity::Left],
			'??' => [110, Associativity::Right],
			'and' => [50, Associativity::Left],
			'xor' => [40, Associativity::Left],
			'or' => [30, Associativity::Left],
			default => throw new \InvalidArgumentException(Helpers::formatCode($operator) . ' is not a binary operator.'),
		};
	}


	/** Whether the operator is `&&`, `||`, `and`, `or` or `xor`, the operators the parts of a condition are chained with. */
	public function isLogical(): bool
	{
		return $this->operator->is([Token::BooleanAnd, Token::BooleanOr, Token::LogicalAnd, Token::LogicalOr, Token::LogicalXor]);
	}
}
