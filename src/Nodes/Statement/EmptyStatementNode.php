<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * Bare semicolon, or a close tag after a terminated statement.
 */
final class EmptyStatementNode extends StatementNode
{
	public const Slots = ['semicolon'];


	/** @internal */
	public function __construct(
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
