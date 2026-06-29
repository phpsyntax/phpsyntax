<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * global statement.
 */
final class GlobalNode extends StatementNode
{
	public const Slots = ['globalKeyword', 'variables', 'semicolon'];


	/** @internal */
	public function __construct(
		public Token $globalKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ExpressionNode> */
		public SeparatedNodeList $variables { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
