<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\Nodes\AttributeGroupNode;
use PhpSyntax\Nodes\FunctionLikeNode;
use PhpSyntax\Nodes\IdentifierNode;
use PhpSyntax\Nodes\MemberNode;
use PhpSyntax\Nodes\ModifiersNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\ParameterNode;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\Statement\BlockNode;
use PhpSyntax\Nodes\TypeNode;
use PhpSyntax\Token;


/**
 * Method declaration; abstract and interface methods end with a semicolon instead of a body.
 */
final class MethodNode extends MemberNode implements FunctionLikeNode
{
	public const Slots = ['attributes', 'modifiers', 'functionKeyword', 'ampersand', 'name', 'openParen', 'parameters', 'closeParen', 'colon', 'returnType', 'body', 'semicolon'];


	/** @internal */
	public function __construct(
		/** @var NodeList<AttributeGroupNode> */
		public NodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ModifiersNode $modifiers { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $functionKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $ampersand { set => $this->prepareSlot(__PROPERTY__, $value); },
		public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ParameterNode> */
		public SeparatedNodeList $parameters { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?TypeNode $returnType { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?BlockNode $body { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/** Whether the method is the constructor, whose name PHP compares without regard to letter case. */
	public function isConstructor(): bool
	{
		return strcasecmp($this->name->text, '__construct') === 0;
	}
}
