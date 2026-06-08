<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * Anonymous class in a `new` expression: `new class(...) extends A implements B { ... }`.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class AnonymousClassNode extends Node implements ClassLikeNode
{
	public const Slots = [
		'attributes', 'modifiers', 'classKeyword', 'arguments', 'extendsKeyword', 'extends', 'implementsKeyword', 'implements', 'openBrace',
		'members', 'closeBrace',
	];

	/** @var PlainNodeList<AttributeGroupNode> */
	public PlainNodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ModifiersNode $modifiers { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $classKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ArgumentListNode $arguments = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $extendsKeyword = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?NameNode $extends = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $implementsKeyword = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var ?SeparatedNodeList<NameNode> */
	public ?SeparatedNodeList $implements = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var PlainNodeList<MemberNode> */
	public PlainNodeList $members { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); }

	public ?IdentifierNode $name { get => null; }


	/**
	 * @internal
	 * @param PlainNodeList<AttributeGroupNode> $attributes
	 * @param ?SeparatedNodeList<NameNode> $implements
	 * @param PlainNodeList<MemberNode> $members
	 */
	public function __construct(
		PlainNodeList $attributes,
		ModifiersNode $modifiers,
		Token $classKeyword,
		?ArgumentListNode $arguments,
		?Token $extendsKeyword,
		?NameNode $extends,
		?Token $implementsKeyword,
		?SeparatedNodeList $implements,
		Token $openBrace,
		PlainNodeList $members,
		Token $closeBrace,
	) {
		$this->attributes = $attributes;
		$this->modifiers = $modifiers;
		$this->classKeyword = $classKeyword;
		$arguments === null || $this->arguments = $arguments;
		$extendsKeyword === null || $this->extendsKeyword = $extendsKeyword;
		$extends === null || $this->extends = $extends;
		$implementsKeyword === null || $this->implementsKeyword = $implementsKeyword;
		$implements === null || $this->implements = $implements;
		$this->openBrace = $openBrace;
		$this->members = $members;
		$this->closeBrace = $closeBrace;
	}
}
