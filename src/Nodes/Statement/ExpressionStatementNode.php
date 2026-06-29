<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * Expression as a statement.
 */
final class ExpressionStatementNode extends StatementNode
{
	public const Slots = ['expression', 'semicolon'];


	/** @internal */
	public function __construct(
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
