<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\AttributeGroupNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\FunctionLikeNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\OperatorNode;
use PhpSyntax\Nodes\ParameterNode;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\TypeNode;
use PhpSyntax\Token;


/**
 * Arrow function fn(...) => expr, optionally static.
 */
final class ArrowFunctionNode extends ExpressionNode implements FunctionLikeNode, OperatorNode
{
	public const Slots = ['attributes', 'staticKeyword', 'fnKeyword', 'ampersand', 'openParen', 'parameters', 'closeParen', 'colon', 'returnType', 'doubleArrow', 'expression'];


	/** @internal */
	public function __construct(
		/** @var NodeList<AttributeGroupNode> */
		public NodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $staticKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $fnKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $ampersand { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ParameterNode> */
		public SeparatedNodeList $parameters { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?TypeNode $returnType { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $doubleArrow { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	public function getPrecedence(): array
	{
		return [10, self::RightAssociative];
	}
}
