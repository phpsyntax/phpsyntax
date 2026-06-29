<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * unset statement.
 */
final class UnsetNode extends StatementNode
{
	public const Slots = ['unsetKeyword', 'openParen', 'variables', 'closeParen', 'semicolon'];


	/** @internal */
	public function __construct(
		public Token $unsetKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ExpressionNode> */
		public SeparatedNodeList $variables { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
