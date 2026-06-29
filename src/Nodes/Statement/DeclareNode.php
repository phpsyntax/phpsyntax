<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\DeclareItemNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * declare statement in its three forms: with a body, with a bare semicolon, or with the alternative syntax.
 */
final class DeclareNode extends StatementNode
{
	public const Slots = ['declareKeyword', 'openParen', 'items', 'closeParen', 'body', 'colon', 'statements', 'endKeyword', 'semicolon'];


	/** @internal */
	public function __construct(
		public Token $declareKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<DeclareItemNode> */
		public SeparatedNodeList $items { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?StatementNode $body { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var ?NodeList<StatementNode> */
		public ?NodeList $statements { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $endKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
