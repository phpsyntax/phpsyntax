<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\AttributeGroupNode;
use PhpSyntax\Nodes\ClassLikeNode;
use PhpSyntax\Nodes\IdentifierNode;
use PhpSyntax\Nodes\MemberNode;
use PhpSyntax\Nodes\ModifiersNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * Class declaration.
 */
final class ClassNode extends StatementNode implements ClassLikeNode
{
	public const Slots = ['attributes', 'modifiers', 'classKeyword', 'name', 'extendsKeyword', 'extends', 'implementsKeyword', 'implements', 'openBrace', 'members', 'closeBrace'];


	/** @internal */
	public function __construct(
		/** @var NodeList<AttributeGroupNode> */
		public NodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ModifiersNode $modifiers { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $classKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $extendsKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?NameNode $extends { set => $this->prepareSlot(__PROPERTY__, $value); },
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
