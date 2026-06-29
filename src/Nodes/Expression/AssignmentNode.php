<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * Assignment `$a = $b`, whose operator is always `=`.
 */
final class AssignmentNode extends ExpressionNode
{
	public const Slots = ['target', 'operator', 'expression'];


	/** @internal */
	public function __construct(
		public ExpressionNode|ListNode $target { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
