<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * return with an optional value.
 */
final class ReturnNode extends StatementNode
{
	public const Slots = ['returnKeyword', 'expression', 'semicolon'];


	/** @internal */
	public function __construct(
		public Token $returnKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
