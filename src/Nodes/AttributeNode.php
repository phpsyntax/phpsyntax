<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;


/**
 * Attribute with optional arguments.
 */
final class AttributeNode extends Node
{
	public const Slots = ['name', 'arguments'];


	/** @internal */
	public function __construct(
		public NameNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ArgumentListNode $arguments { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
