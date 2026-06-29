<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Token;


/**
 * Anonymous class in a new expression: new class(...) extends A implements B { ... }.
 */
final class AnonymousClassNode extends Node implements ClassLikeNode
{
	public const Slots = ['attributes', 'modifiers', 'classKeyword', 'arguments', 'extendsKeyword', 'extends', 'implementsKeyword', 'implements', 'openBrace', 'members', 'closeBrace'];

	public ?IdentifierNode $name { get => null; }


	/** @internal */
	public function __construct(
		/** @var NodeList<AttributeGroupNode> */
		public NodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ModifiersNode $modifiers { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $classKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ArgumentListNode $arguments { set => $this->prepareSlot(__PROPERTY__, $value); },
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
