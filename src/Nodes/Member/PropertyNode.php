<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\Nodes\AttributeGroupNode;
use PhpSyntax\Nodes\MemberNode;
use PhpSyntax\Nodes\ModifiersNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\TypeNode;
use PhpSyntax\Token;


/**
 * Property declaration; one or more properties, optionally with hooks.
 */
final class PropertyNode extends MemberNode
{
	public const Slots = ['attributes', 'modifiers', 'type', 'items', 'semicolon', 'openBrace', 'hooks', 'closeBrace'];


	/** @internal */
	public function __construct(
		/** @var NodeList<AttributeGroupNode> */
		public NodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ModifiersNode $modifiers { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?TypeNode $type { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<PropertyItemNode> */
		public SeparatedNodeList $items { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var ?NodeList<PropertyHookNode> */
		public ?NodeList $hooks { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
