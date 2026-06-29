<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * yield, yield $value or yield $key => $value.
 */
final class YieldNode extends ExpressionNode
{
	public const Slots = ['yieldKeyword', 'key', 'doubleArrow', 'value'];


	/** @internal */
	public function __construct(
		public Token $yieldKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ExpressionNode $key { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $doubleArrow { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ExpressionNode $value { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
