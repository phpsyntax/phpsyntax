<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * Binary operation; the operator token tells which (arithmetic, comparison, logical, bitwise, concatenation, coalesce, pipe).
 */
final class BinaryOpNode extends ExpressionNode
{
	public const Slots = ['left', 'operator', 'right'];


	/** @internal */
	public function __construct(
		public ExpressionNode $left { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $right { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
