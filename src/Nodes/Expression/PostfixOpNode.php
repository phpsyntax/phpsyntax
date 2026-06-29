<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * Postfix increment or decrement.
 */
final class PostfixOpNode extends ExpressionNode
{
	public const Slots = ['target', 'operator'];


	/** @internal */
	public function __construct(
		public ExpressionNode $target { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
