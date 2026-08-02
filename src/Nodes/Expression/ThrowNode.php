<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\OperatorNode;
use PhpSyntax\Token;


/**
 * throw expression.
 */
final class ThrowNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['throwKeyword', 'expression'];


	/** @internal */
	public function __construct(
		public Token $throwKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	public function getPrecedence(): array
	{
		return [10, self::RightAssociative];
	}
}
