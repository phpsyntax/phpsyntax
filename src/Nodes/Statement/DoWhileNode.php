<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * do-while loop.
 */
final class DoWhileNode extends StatementNode
{
	public const Slots = ['doKeyword', 'body', 'whileKeyword', 'openParen', 'condition', 'closeParen', 'semicolon'];


	/** @internal */
	public function __construct(
		public Token $doKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public StatementNode $body { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $whileKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $condition { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
