<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * Unary operation written before its operand: +, -, !, ~, @; ++ and -- are a PrefixOpNode, the way the grammar tells them apart.
 */
final class UnaryOpNode extends ExpressionNode
{
	public const Slots = ['operator', 'expression'];


	/** @internal */
	public function __construct(
		public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
