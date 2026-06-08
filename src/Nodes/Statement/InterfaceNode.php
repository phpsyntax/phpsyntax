<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{AttributeGroupNode, ClassLikeNode, IdentifierNode, MemberNode, NameNode, PlainNodeList, SeparatedNodeList, StatementNode};
use PhpSyntax\Token;


/**
 * Interface declaration.
 */
final class InterfaceNode extends StatementNode implements ClassLikeNode
{
	public const Slots = ['attributes', 'interfaceKeyword', 'name', 'extendsKeyword', 'extends', 'openBrace', 'members', 'closeBrace'];

	/** @var PlainNodeList<AttributeGroupNode> */
	public PlainNodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $interfaceKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $extendsKeyword = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var ?SeparatedNodeList<NameNode> */
	public ?SeparatedNodeList $extends = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var PlainNodeList<MemberNode> */
	public PlainNodeList $members { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param PlainNodeList<AttributeGroupNode> $attributes
	 * @param ?SeparatedNodeList<NameNode> $extends
	 * @param PlainNodeList<MemberNode> $members
	 */
	public function __construct(
		PlainNodeList $attributes,
		Token $interfaceKeyword,
		IdentifierNode $name,
		?Token $extendsKeyword,
		?SeparatedNodeList $extends,
		Token $openBrace,
		PlainNodeList $members,
		Token $closeBrace,
	) {
		$this->attributes = $attributes;
		$this->interfaceKeyword = $interfaceKeyword;
		$this->name = $name;
		$extendsKeyword === null || $this->extendsKeyword = $extendsKeyword;
		$extends === null || $this->extends = $extends;
		$this->openBrace = $openBrace;
		$this->members = $members;
		$this->closeBrace = $closeBrace;
	}
}
