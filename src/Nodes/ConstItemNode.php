<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Token;


/**
 * Constant of a const statement or a class constant declaration.
 */
final class ConstItemNode extends Node
{
	public const Slots = ['name', 'equals', 'value'];


	/** @internal */
	public function __construct(
		public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $equals { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $value { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
