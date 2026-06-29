<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * Statements in braces.
 */
final class BlockNode extends StatementNode
{
	public const Slots = ['openBrace', 'statements', 'closeBrace'];


	/** @internal */
	public function __construct(
		public Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var NodeList<StatementNode> */
		public NodeList $statements { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
