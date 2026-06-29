<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * yield from expression.
 */
final class YieldFromNode extends ExpressionNode
{
	public const Slots = ['yieldFromKeyword', 'expression'];


	/** @internal */
	public function __construct(
		public Token $yieldFromKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
