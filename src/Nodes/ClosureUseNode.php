<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Token;


/**
 * Variable captured by a closure, optionally by reference.
 */
final class ClosureUseNode extends Node
{
	public const Slots = ['ampersand', 'variable'];


	/** @internal */
	public function __construct(
		public ?Token $ampersand { set => $this->prepareSlot(__PROPERTY__, $value); },
		public VariableNode $variable { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
