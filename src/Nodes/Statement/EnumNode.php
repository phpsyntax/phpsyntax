<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{AttributeGroupNode, ClassLikeNode, IdentifierNode, MemberNode, NameNode, PlainNodeList, SeparatedNodeList, StatementNode, TypeNode};
use PhpSyntax\Token;


/**
 * Enum declaration, optionally backed by a scalar type.
 */
final class EnumNode extends StatementNode implements ClassLikeNode
{
	public const Slots = [
		'attributes', 'enumKeyword', 'name', 'colon', 'backingType', 'implementsKeyword', 'implements', 'openBrace', 'members',
		'closeBrace',
	];

	/** @var PlainNodeList<AttributeGroupNode> */
	public PlainNodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $enumKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $colon = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?TypeNode $backingType = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $implementsKeyword = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var ?SeparatedNodeList<NameNode> */
	public ?SeparatedNodeList $implements = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var PlainNodeList<MemberNode> */
	public PlainNodeList $members { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param PlainNodeList<AttributeGroupNode> $attributes
	 * @param ?SeparatedNodeList<NameNode> $implements
	 * @param PlainNodeList<MemberNode> $members
	 */
	public function __construct(
		PlainNodeList $attributes,
		Token $enumKeyword,
		IdentifierNode $name,
		?Token $colon,
		?TypeNode $backingType,
		?Token $implementsKeyword,
		?SeparatedNodeList $implements,
		Token $openBrace,
		PlainNodeList $members,
		Token $closeBrace,
	) {
		$this->attributes = $attributes;
		$this->enumKeyword = $enumKeyword;
		$this->name = $name;
		$colon === null || $this->colon = $colon;
		$backingType === null || $this->backingType = $backingType;
		$implementsKeyword === null || $this->implementsKeyword = $implementsKeyword;
		$implements === null || $this->implements = $implements;
		$this->openBrace = $openBrace;
		$this->members = $members;
		$this->closeBrace = $closeBrace;
	}
}
