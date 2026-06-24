<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\Nodes\AttributeGroupNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\IdentifierNode;
use PhpSyntax\Nodes\MemberNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Token;


/**
 * Enum case with an optional backing value.
 */
final class EnumCaseNode extends MemberNode
{
	public const Slots = ['attributes', 'caseKeyword', 'name', 'equals', 'value', 'semicolon'];


	/** @internal */
	public function __construct(
		/** @var NodeList<AttributeGroupNode> */
		public NodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $caseKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $equals { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ExpressionNode $value { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
