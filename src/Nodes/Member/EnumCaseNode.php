<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\Nodes\{AttributeAwareNode, AttributeGroupNode, ExpressionNode, IdentifierNode, MemberNode, PlainNodeList};
use PhpSyntax\Token;


/**
 * Enum case with an optional backing value.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class EnumCaseNode extends MemberNode implements AttributeAwareNode
{
	public const Slots = ['attributes', 'caseKeyword', 'name', 'equals', 'value', 'semicolon'];

	/** @var PlainNodeList<AttributeGroupNode> */
	public PlainNodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $caseKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $equals = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ExpressionNode $value = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param PlainNodeList<AttributeGroupNode> $attributes
	 */
	public function __construct(
		PlainNodeList $attributes,
		Token $caseKeyword,
		IdentifierNode $name,
		?Token $equals,
		?ExpressionNode $value,
		Token $semicolon,
	) {
		$this->attributes = $attributes;
		$this->caseKeyword = $caseKeyword;
		$this->name = $name;
		$equals === null || $this->equals = $equals;
		$value === null || $this->value = $value;
		$this->semicolon = $semicolon;
	}
}
