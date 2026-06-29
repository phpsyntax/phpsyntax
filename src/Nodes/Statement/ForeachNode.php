<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\Expression\ListNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * foreach loop in either syntax.
 */
final class ForeachNode extends StatementNode
{
	public const Slots = ['foreachKeyword', 'openParen', 'expression', 'asKeyword', 'key', 'doubleArrow', 'ampersand', 'value', 'closeParen', 'body', 'colon', 'statements', 'endKeyword', 'semicolon'];


	/** @internal */
	public function __construct(
		public Token $foreachKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $asKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ExpressionNode $key { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $doubleArrow { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $ampersand { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode|ListNode $value { set => $this->prepareSlot(__PROPERTY__, $value); },
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
