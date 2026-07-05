<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * for loop in either syntax.
 */
final class ForNode extends StatementNode
{
	public const Slots = ['forKeyword', 'openParen', 'initializers', 'firstSemicolon', 'conditions', 'secondSemicolon', 'updates', 'closeParen', 'body', 'colon', 'statements', 'endKeyword', 'semicolon'];


	/** @internal */
	public function __construct(
		public Token $forKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ExpressionNode> */
		public SeparatedNodeList $initializers { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $firstSemicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ExpressionNode> */
		public SeparatedNodeList $conditions { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $secondSemicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ExpressionNode> */
		public SeparatedNodeList $updates { set => $this->prepareSlot(__PROPERTY__, $value); },
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
