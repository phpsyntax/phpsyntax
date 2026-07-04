<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\OperatorNode;
use PhpSyntax\Token;


/**
 * Prefix increment or decrement.
 */
final class PrefixOpNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['operator', 'target'];


	/** @internal */
	public function __construct(
		public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $target { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	public function getPrecedence(): array
	{
		return [240, self::RightAssociative];
	}
}
