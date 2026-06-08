<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{AttributeGroupNode, ClassLikeNode, IdentifierNode, MemberNode, PlainNodeList, StatementNode};
use PhpSyntax\Token;


/**
 * Trait declaration.
 */
final class TraitNode extends StatementNode implements ClassLikeNode
{
	public const Slots = ['attributes', 'traitKeyword', 'name', 'openBrace', 'members', 'closeBrace'];

	/** @var PlainNodeList<AttributeGroupNode> */
	public PlainNodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $traitKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var PlainNodeList<MemberNode> */
	public PlainNodeList $members { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param PlainNodeList<AttributeGroupNode> $attributes
	 * @param PlainNodeList<MemberNode> $members
	 */
	public function __construct(
		PlainNodeList $attributes,
		Token $traitKeyword,
		IdentifierNode $name,
		Token $openBrace,
		PlainNodeList $members,
		Token $closeBrace,
	) {
		$this->attributes = $attributes;
		$this->traitKeyword = $traitKeyword;
		$this->name = $name;
		$this->openBrace = $openBrace;
		$this->members = $members;
		$this->closeBrace = $closeBrace;
	}
}
