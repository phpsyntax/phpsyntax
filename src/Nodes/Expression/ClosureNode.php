<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\AttributeGroupNode;
use PhpSyntax\Nodes\ClosureUsesNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\FunctionLikeNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\ParameterNode;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\Statement\BlockNode;
use PhpSyntax\Nodes\TypeNode;
use PhpSyntax\Token;


/**
 * Anonymous function, optionally static, with captured variables.
 */
final class ClosureNode extends ExpressionNode implements FunctionLikeNode
{
	public const Slots = ['attributes', 'staticKeyword', 'functionKeyword', 'ampersand', 'openParen', 'parameters', 'closeParen', 'uses', 'colon', 'returnType', 'body'];


	/** @internal */
	public function __construct(
		/** @var NodeList<AttributeGroupNode> */
		public NodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $staticKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $functionKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $ampersand { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ParameterNode> */
		public SeparatedNodeList $parameters { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ClosureUsesNode $uses { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?TypeNode $returnType { set => $this->prepareSlot(__PROPERTY__, $value); },
		public BlockNode $body { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
