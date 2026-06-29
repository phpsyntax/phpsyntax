<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * Prefix increment or decrement.
 */
final class PrefixOpNode extends ExpressionNode
{
	public const Slots = ['operator', 'target'];


	/** @internal */
	public function __construct(
		public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $target { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
