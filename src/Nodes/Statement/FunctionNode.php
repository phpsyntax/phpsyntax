<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{AttributeGroupNode, FunctionLikeNode, IdentifierNode, ParameterNode, PlainNodeList, SeparatedNodeList, StatementNode, TypeNode};
use PhpSyntax\Token;


/**
 * Function declaration.
 */
final class FunctionNode extends StatementNode implements FunctionLikeNode
{
	public const Slots = ['attributes', 'functionKeyword', 'ampersand', 'name', 'openParen', 'parameters', 'closeParen', 'colon', 'returnType', 'body'];

	/** @var PlainNodeList<AttributeGroupNode> */
	public PlainNodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $functionKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $ampersand = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<ParameterNode> */
	public SeparatedNodeList $parameters { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $colon = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?TypeNode $returnType = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public BlockNode $body { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param PlainNodeList<AttributeGroupNode> $attributes
	 * @param SeparatedNodeList<ParameterNode> $parameters
	 */
	public function __construct(
		PlainNodeList $attributes,
		Token $functionKeyword,
		?Token $ampersand,
		IdentifierNode $name,
		Token $openParen,
		SeparatedNodeList $parameters,
		Token $closeParen,
		?Token $colon,
		?TypeNode $returnType,
		BlockNode $body,
	) {
		$this->attributes = $attributes;
		$this->functionKeyword = $functionKeyword;
		$ampersand === null || $this->ampersand = $ampersand;
		$this->name = $name;
		$this->openParen = $openParen;
		$this->parameters = $parameters;
		$this->closeParen = $closeParen;
		$colon === null || $this->colon = $colon;
		$returnType === null || $this->returnType = $returnType;
		$this->body = $body;
	}
}
