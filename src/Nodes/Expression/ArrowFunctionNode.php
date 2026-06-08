<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Token};
use PhpSyntax\Nodes\{AnonymousFunctionNode, AttributeGroupNode, ExpressionNode, ParameterNode, PlainNodeList, RightExtendingNode, SeparatedNodeList, TypeNode};


/**
 * Arrow function `fn(...) => expr`, optionally `static`.
 */
final class ArrowFunctionNode extends ExpressionNode implements AnonymousFunctionNode, RightExtendingNode
{
	public const Slots = [
		'attributes', 'staticKeyword', 'fnKeyword', 'ampersand', 'openParen', 'parameters', 'closeParen', 'colon', 'returnType',
		'doubleArrow', 'expression',
	];

	/** @var PlainNodeList<AttributeGroupNode> */
	public PlainNodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $staticKeyword = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $fnKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $ampersand = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<ParameterNode> */
	public SeparatedNodeList $parameters { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $colon = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?TypeNode $returnType = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $doubleArrow { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); }

	public int $precedence { get => 10; }
	public Associativity $associativity { get => Associativity::Right; }


	/**
	 * @internal
	 * @param PlainNodeList<AttributeGroupNode> $attributes
	 * @param SeparatedNodeList<ParameterNode> $parameters
	 */
	public function __construct(
		PlainNodeList $attributes,
		?Token $staticKeyword,
		Token $fnKeyword,
		?Token $ampersand,
		Token $openParen,
		SeparatedNodeList $parameters,
		Token $closeParen,
		?Token $colon,
		?TypeNode $returnType,
		Token $doubleArrow,
		ExpressionNode $expression,
	) {
		$this->attributes = $attributes;
		$staticKeyword === null || $this->staticKeyword = $staticKeyword;
		$this->fnKeyword = $fnKeyword;
		$ampersand === null || $this->ampersand = $ampersand;
		$this->openParen = $openParen;
		$this->parameters = $parameters;
		$this->closeParen = $closeParen;
		$colon === null || $this->colon = $colon;
		$returnType === null || $this->returnType = $returnType;
		$this->doubleArrow = $doubleArrow;
		$this->expression = $expression;
	}
}
