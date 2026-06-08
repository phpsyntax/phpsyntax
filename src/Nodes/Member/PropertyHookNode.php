<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\{AttributeGroupNode, ExpressionNode, FunctionLikeNode, IdentifierNode, ModifiersNode, NodeList, ParameterNode, SeparatedNodeList, TypeNode};
use PhpSyntax\Nodes\Statement\BlockNode;


/**
 * Property hook (get, set) with a block body, an arrow body or none.
 */
final class PropertyHookNode extends Node implements FunctionLikeNode
{
	public const Slots = [
		'attributes', 'modifiers', 'ampersand', 'name', 'openParen', 'parameters', 'closeParen', 'body', 'doubleArrow', 'expression',
		'semicolon',
	];

	public ?TypeNode $returnType { get => null; }


	/** @internal */
	public function __construct(
		/** @var NodeList<AttributeGroupNode> */
		public NodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ModifiersNode $modifiers { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $ampersand { set => $this->prepareSlot(__PROPERTY__, $value); },
		public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var ?SeparatedNodeList<ParameterNode> */
		public ?SeparatedNodeList $parameters { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?BlockNode $body { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $doubleArrow { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
