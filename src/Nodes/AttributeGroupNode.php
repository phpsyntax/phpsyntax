<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Token;


/**
 * One #[...] group of attributes.
 */
final class AttributeGroupNode extends Node
{
	public const Slots = ['openAttribute', 'attributes', 'closeBracket'];


	/** @internal */
	public function __construct(
		public Token $openAttribute { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<AttributeNode> */
		public SeparatedNodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeBracket { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
