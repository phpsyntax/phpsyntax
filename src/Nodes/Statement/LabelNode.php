<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\IdentifierNode;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * Label for goto.
 */
final class LabelNode extends StatementNode
{
	public const Slots = ['name', 'colon'];


	/** @internal */
	public function __construct(
		public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
