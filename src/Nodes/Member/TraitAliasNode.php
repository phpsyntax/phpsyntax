<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\Nodes\{IdentifierNode, NameNode};
use PhpSyntax\Token;


/**
 * Trait adaptation `m as [modifier] [alias]`.
 */
final class TraitAliasNode extends TraitAdaptationNode
{
	public const Slots = ['trait', 'doubleColon', 'method', 'asKeyword', 'modifier', 'alias', 'semicolon'];

	public ?NameNode $trait = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $doubleColon = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public IdentifierNode $method { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $asKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $modifier = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?IdentifierNode $alias = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); }


	/** @internal */
	public function __construct(
		?NameNode $trait,
		?Token $doubleColon,
		IdentifierNode $method,
		Token $asKeyword,
		?Token $modifier,
		?IdentifierNode $alias,
		Token $semicolon,
	) {
		$trait === null || $this->trait = $trait;
		$doubleColon === null || $this->doubleColon = $doubleColon;
		$this->method = $method;
		$this->asKeyword = $asKeyword;
		$modifier === null || $this->modifier = $modifier;
		$alias === null || $this->alias = $alias;
		$this->semicolon = $semicolon;
	}
}
