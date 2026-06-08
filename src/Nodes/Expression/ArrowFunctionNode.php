<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{AttributeGroupNode, ExpressionNode, FunctionLikeNode, NodeList, ParameterNode, RightExtendingNode, SeparatedNodeList, TypeNode};
use PhpSyntax\Token;


/**
 * Arrow function `fn(...) => expr`, optionally `static`.
 */
final class ArrowFunctionNode extends ExpressionNode implements FunctionLikeNode, RightExtendingNode
{
	public const Slots = [
		'attributes', 'staticKeyword', 'fnKeyword', 'ampersand', 'openParen', 'parameters', 'closeParen', 'colon', 'returnType',
		'doubleArrow', 'expression',
	];


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
