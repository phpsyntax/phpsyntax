<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Lexer\Lexer;
use PhpSyntax\Nodes\{ExpressionNode, OperatorNode};
use PhpSyntax\{Token, Trivia, TriviaKind};


/**
 * Binary operation; the operator token tells which (arithmetic, comparison, logical, bitwise, concatenation, coalesce, pipe).
 */
final class BinaryOpNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['left', 'operator', 'right'];


	/** @internal */
	public function __construct(
		public ExpressionNode $left { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $right { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/**
	 * The operation on the two operands, each in parentheses where it binds looser than its side of the operator takes.
	 * @throws \InvalidArgumentException  for what is no binary operator
	 */
	public static function of(ExpressionNode $left, string $operator, ExpressionNode $right): self
	{
		try {
			self::resolvePrecedence($operator);
		} catch (\LogicException $e) {
			throw new \InvalidArgumentException($e->getMessage());
		}

		self::checkDetached($left, $right);
		static $lexer = new Lexer;
		$space = [new Trivia(TriviaKind::Whitespace, ' ')];
		$node = new self(
			ParenthesizedNode::of($left),
			new Token($lexer->tokenize('<?php ' . $operator, withPositions: false)[0]->kind, $operator)->setTrailingTrivia($space),
			ParenthesizedNode::of($right),
		);

		foreach ([$node->left, $node->right] as $operand) {
			if ($operand instanceof ParenthesizedNode && $operand->isRedundant()) {
				$operand->replaceWith($operand->expression);
			}
		}

		$node->left->setEdgeTrivia(trailing: $space);
		return $node;
	}


	public function getPrecedence(): array
	{
		return self::resolvePrecedence($this->operator->text);
	}


	/** @return array{int, self::LeftAssociative|self::NonAssociative|self::RightAssociative} */
	private static function resolvePrecedence(string $operator): array
	{
		return match (strtolower($operator)) {
			'**' => [250, self::RightAssociative],
			'*', '/', '%' => [210, self::LeftAssociative],
			'+', '-' => [200, self::LeftAssociative],
			'<<', '>>' => [190, self::LeftAssociative],
			'.' => [185, self::LeftAssociative],
			'|>' => [183, self::LeftAssociative],
			'<', '<=', '>', '>=' => [180, self::NonAssociative],
			'==', '!=', '<>', '===', '!==', '<=>' => [170, self::NonAssociative],
			'&' => [160, self::LeftAssociative],
			'^' => [150, self::LeftAssociative],
			'|' => [140, self::LeftAssociative],
			'&&' => [130, self::LeftAssociative],
			'||' => [120, self::LeftAssociative],
			'??' => [110, self::RightAssociative],
			'and' => [50, self::LeftAssociative],
			'xor' => [40, self::LeftAssociative],
			'or' => [30, self::LeftAssociative],
			default => throw new \LogicException("'$operator' is not a binary operator."),
		};
	}
}
