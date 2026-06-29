<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\AttributeGroupNode;
use PhpSyntax\Nodes\ClassLikeNode;
use PhpSyntax\Nodes\IdentifierNode;
use PhpSyntax\Nodes\MemberNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * Trait declaration.
 */
final class TraitNode extends StatementNode implements ClassLikeNode
{
	public const Slots = ['attributes', 'traitKeyword', 'name', 'openBrace', 'members', 'closeBrace'];


	/** @internal */
	public function __construct(
		/** @var NodeList<AttributeGroupNode> */
		public NodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $traitKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var NodeList<MemberNode> */
		public NodeList $members { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
