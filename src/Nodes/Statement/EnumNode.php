<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\AttributeGroupNode;
use PhpSyntax\Nodes\ClassLikeNode;
use PhpSyntax\Nodes\IdentifierNode;
use PhpSyntax\Nodes\MemberNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Nodes\TypeNode;
use PhpSyntax\Token;


/**
 * Enum declaration, optionally backed by a scalar type.
 */
final class EnumNode extends StatementNode implements ClassLikeNode
{
	public const Slots = ['attributes', 'enumKeyword', 'name', 'colon', 'scalarType', 'implementsKeyword', 'implements', 'openBrace', 'members', 'closeBrace'];


	/** @internal */
	public function __construct(
		/** @var NodeList<AttributeGroupNode> */
		public NodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $enumKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?TypeNode $scalarType { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $implementsKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var ?SeparatedNodeList<NameNode> */
		public ?SeparatedNodeList $implements { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var NodeList<MemberNode> */
		public NodeList $members { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
