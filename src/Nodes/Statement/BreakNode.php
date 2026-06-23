<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * break with an optional level.
 */
final class BreakNode extends StatementNode
{
	public const Slots = ['breakKeyword', 'expression', 'semicolon'];


	/** @internal */
	public function __construct(
		public Token $breakKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
