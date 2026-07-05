<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Token;


/**
 * elseif branch, in either syntax.
 */
final class ElseIfNode extends Node
{
	public const Slots = ['elseifKeyword', 'openParen', 'condition', 'closeParen', 'body', 'colon', 'statements'];


	/** @internal */
	public function __construct(
		public Token $elseifKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $condition { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?StatementNode $body { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var ?NodeList<StatementNode> */
		public ?NodeList $statements { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
