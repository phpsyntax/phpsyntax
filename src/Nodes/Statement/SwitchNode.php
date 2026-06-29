<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\CaseNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * switch statement in either syntax; a semicolon may precede the first case.
 */
final class SwitchNode extends StatementNode
{
	public const Slots = ['switchKeyword', 'openParen', 'subject', 'closeParen', 'openBrace', 'colon', 'leadingSemicolon', 'cases', 'closeBrace', 'endKeyword', 'semicolon'];


	/** @internal */
	public function __construct(
		public Token $switchKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $subject { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $leadingSemicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var NodeList<CaseNode> */
		public NodeList $cases { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $endKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
