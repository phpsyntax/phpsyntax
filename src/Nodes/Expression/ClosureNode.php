<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{AnonymousFunctionNode, AttributeGroupNode, ClosureUseListNode, ExpressionNode, ParameterNode, PlainNodeList, SeparatedNodeList, TypeNode};
use PhpSyntax\Nodes\Statement\BlockNode;
use PhpSyntax\{Surgery, Token};


/**
 * Anonymous function, optionally `static`, with captured variables.
 */
final class ClosureNode extends ExpressionNode implements AnonymousFunctionNode
{
	public const Slots = [
		'attributes', 'staticKeyword', 'functionKeyword', 'ampersand', 'openParen', 'parameters', 'closeParen', 'uses', 'colon',
		'returnType', 'body',
	];

	/** @var PlainNodeList<AttributeGroupNode> */
	public PlainNodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $staticKeyword = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $functionKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $ampersand = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<ParameterNode> */
	public SeparatedNodeList $parameters { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ClosureUseListNode $uses = null { set => $this->prepareSlot(__PROPERTY__, $value); }
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
		?Token $staticKeyword,
		Token $functionKeyword,
		?Token $ampersand,
		Token $openParen,
		SeparatedNodeList $parameters,
		Token $closeParen,
		?ClosureUseListNode $uses,
		?Token $colon,
		?TypeNode $returnType,
		BlockNode $body,
	) {
		$this->attributes = $attributes;
		$staticKeyword === null || $this->staticKeyword = $staticKeyword;
		$this->functionKeyword = $functionKeyword;
		$ampersand === null || $this->ampersand = $ampersand;
		$this->openParen = $openParen;
		$this->parameters = $parameters;
		$this->closeParen = $closeParen;
		$uses === null || $this->uses = $uses;
		$colon === null || $this->colon = $colon;
		$returnType === null || $this->returnType = $returnType;
		$this->body = $body;
	}


	/** Writes the return type with its colon, or removes both, as `FunctionLikeNode::setReturnType()` says. */
	public function setReturnType(?TypeNode $type): static
	{
		Surgery::writeReturnType($this, $this->uses->closeParen ?? $this->closeParen, $type);
		return $this;
	}


	/**
	 * Names of the variables the closure captures with `use (...)`, without the dollar sign.
	 * @return list<string>
	 */
	public function getCapturedVariableNames(): array
	{
		$names = [];
		foreach ($this->uses->items ?? [] as $use) {
			$names[] = (string) $use->variable->plainName; // the grammar takes a plain variable there
		}

		return $names;
	}
}
