<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\OperatorNode;
use PhpSyntax\Token;


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


	public function getPrecedence(): array
	{
		return match (strtolower($this->operator->text)) {
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
			default => throw new \LogicException("'{$this->operator->text}' is no binary operator."),
		};
	}
}
