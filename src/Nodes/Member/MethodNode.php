<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\Nodes\{AttributeGroupNode, FunctionLikeNode, IdentifierNode, MemberNode, ModifiersNode, ParameterNode, PlainNodeList, SeparatedNodeList, TypeNode};
use PhpSyntax\Nodes\Statement\BlockNode;
use PhpSyntax\{Surgery, Token};


/**
 * Method declaration; abstract and interface methods end with a semicolon instead of a body.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class MethodNode extends MemberNode implements FunctionLikeNode
{
	public const Slots = [
		'attributes', 'modifiers', 'functionKeyword', 'ampersand', 'name', 'openParen', 'parameters', 'closeParen', 'colon', 'returnType',
		'body', 'semicolon',
	];

	/** @var PlainNodeList<AttributeGroupNode> */
	public PlainNodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ModifiersNode $modifiers { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $functionKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $ampersand = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<ParameterNode> */
	public SeparatedNodeList $parameters { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $colon = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?TypeNode $returnType = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?BlockNode $body = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $semicolon = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param PlainNodeList<AttributeGroupNode> $attributes
	 * @param SeparatedNodeList<ParameterNode> $parameters
	 */
	public function __construct(
		PlainNodeList $attributes,
		ModifiersNode $modifiers,
		Token $functionKeyword,
		?Token $ampersand,
		IdentifierNode $name,
		Token $openParen,
		SeparatedNodeList $parameters,
		Token $closeParen,
		?Token $colon,
		?TypeNode $returnType,
		?BlockNode $body,
		?Token $semicolon,
	) {
		$this->attributes = $attributes;
		$this->modifiers = $modifiers;
		$this->functionKeyword = $functionKeyword;
		$ampersand === null || $this->ampersand = $ampersand;
		$this->name = $name;
		$this->openParen = $openParen;
		$this->parameters = $parameters;
		$this->closeParen = $closeParen;
		$colon === null || $this->colon = $colon;
		$returnType === null || $this->returnType = $returnType;
		$body === null || $this->body = $body;
		$semicolon === null || $this->semicolon = $semicolon;
	}


	/** Writes the return type with its colon, or removes both, as `FunctionLikeNode::setReturnType()` says. */
	public function setReturnType(?TypeNode $type): static
	{
		Surgery::writeReturnType($this, $this->closeParen, $type);
		return $this;
	}


	/** Whether the method is the constructor, whose name PHP compares without regard to letter case. */
	public function isConstructor(): bool
	{
		return strcasecmp($this->name->text, '__construct') === 0;
	}
}
