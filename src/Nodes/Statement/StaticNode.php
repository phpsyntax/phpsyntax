<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Nodes\StaticVariableNode;
use PhpSyntax\Token;


/**
 * static variable declaration.
 */
final class StaticNode extends StatementNode
{
	public const Slots = ['staticKeyword', 'variables', 'semicolon'];


	/** @internal */
	public function __construct(
		public Token $staticKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<StaticVariableNode> */
		public SeparatedNodeList $variables { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
