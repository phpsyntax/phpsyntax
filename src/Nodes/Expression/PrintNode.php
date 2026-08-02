<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\OperatorNode;
use PhpSyntax\Token;


/**
 * print expression.
 */
final class PrintNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['printKeyword', 'expression'];


	/** @internal */
	public function __construct(
		public Token $printKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	public function getPrecedence(): array
	{
		return [60, self::RightAssociative];
	}
}
