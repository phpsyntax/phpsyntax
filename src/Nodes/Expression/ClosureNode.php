<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{AttributeGroupNode, ClosureUsesNode, ExpressionNode, FunctionLikeNode, NodeList, ParameterNode, SeparatedNodeList, TypeNode};
use PhpSyntax\Nodes\Statement\BlockNode;
use PhpSyntax\Token;


/**
 * Anonymous function, optionally `static`, with captured variables.
 */
final class ClosureNode extends ExpressionNode implements FunctionLikeNode
{
	public const Slots = [
		'attributes', 'staticKeyword', 'functionKeyword', 'ampersand', 'openParen', 'parameters', 'closeParen', 'uses', 'colon',
		'returnType', 'body',
	];


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
