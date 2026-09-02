<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\{AttributeGroupNode, ExpressionNode, FunctionLikeNode, IdentifierNode, ModifiersNode, ParameterNode, PlainNodeList, SeparatedNodeList, TypeNode};
use PhpSyntax\Nodes\Statement\BlockNode;


/**
 * Property hook (get, set) with a block body, an arrow body or none.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class PropertyHookNode extends Node implements FunctionLikeNode
{
	public const Slots = [
		'attributes', 'modifiers', 'ampersand', 'name', 'openParen', 'parameters', 'closeParen', 'body', 'doubleArrow', 'expression',
		'semicolon',
	];

	/** @var PlainNodeList<AttributeGroupNode> */
	public PlainNodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ModifiersNode $modifiers { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $ampersand = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $openParen = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var ?SeparatedNodeList<ParameterNode> */
	public ?SeparatedNodeList $parameters = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $closeParen = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?BlockNode $body = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $doubleArrow = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ExpressionNode $expression = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $semicolon = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	public ?TypeNode $returnType { get => null; }


	/**
	 * @internal
	 * @param PlainNodeList<AttributeGroupNode> $attributes
	 * @param ?SeparatedNodeList<ParameterNode> $parameters
	 */
	public function __construct(
		PlainNodeList $attributes,
		ModifiersNode $modifiers,
		?Token $ampersand,
		IdentifierNode $name,
		?Token $openParen,
		?SeparatedNodeList $parameters,
		?Token $closeParen,
		?BlockNode $body,
		?Token $doubleArrow,
		?ExpressionNode $expression,
		?Token $semicolon,
	) {
		$this->attributes = $attributes;
		$this->modifiers = $modifiers;
		$ampersand === null || $this->ampersand = $ampersand;
		$this->name = $name;
		$openParen === null || $this->openParen = $openParen;
		$parameters === null || $this->parameters = $parameters;
		$closeParen === null || $this->closeParen = $closeParen;
		$body === null || $this->body = $body;
		$doubleArrow === null || $this->doubleArrow = $doubleArrow;
		$expression === null || $this->expression = $expression;
		$semicolon === null || $this->semicolon = $semicolon;
	}


	/** @throws \LogicException  always, a property hook has no return type */
	public function setReturnType(?TypeNode $type): static
	{
		throw new \LogicException('A property hook has no return type.');
	}
}
