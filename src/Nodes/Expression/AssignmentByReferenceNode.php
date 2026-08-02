<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\OperatorNode;
use PhpSyntax\Token;


/**
 * Assignment by reference `$a = &$b`; the grammar takes a variable or a new expression on the right.
 */
final class AssignmentByReferenceNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['target', 'equals', 'ampersand', 'expression'];


	/** @internal */
	public function __construct(
		public ExpressionNode $target { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $equals { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $ampersand { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	public function getPrecedence(): array
	{
		return [90, self::RightAssociative];
	}
}
