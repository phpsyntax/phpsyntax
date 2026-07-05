<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Token;


/**
 * Variable of a static statement with an optional initializer.
 */
final class StaticVariableNode extends Node
{
	public const Slots = ['variable', 'equals', 'default'];


	/** @internal */
	public function __construct(
		public VariableNode $variable { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $equals { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ExpressionNode $default { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
