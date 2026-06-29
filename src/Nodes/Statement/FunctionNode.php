<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\AttributeGroupNode;
use PhpSyntax\Nodes\FunctionLikeNode;
use PhpSyntax\Nodes\IdentifierNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\ParameterNode;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Nodes\TypeNode;
use PhpSyntax\Token;


/**
 * Function declaration.
 */
final class FunctionNode extends StatementNode implements FunctionLikeNode
{
	public const Slots = ['attributes', 'functionKeyword', 'ampersand', 'name', 'openParen', 'parameters', 'closeParen', 'colon', 'returnType', 'body'];


	/** @internal */
	public function __construct(
		/** @var NodeList<AttributeGroupNode> */
		public NodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $functionKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $ampersand { set => $this->prepareSlot(__PROPERTY__, $value); },
		public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ParameterNode> */
		public SeparatedNodeList $parameters { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?TypeNode $returnType { set => $this->prepareSlot(__PROPERTY__, $value); },
		public BlockNode $body { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
