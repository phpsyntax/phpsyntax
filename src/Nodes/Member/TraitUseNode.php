<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\Nodes\{MemberNode, NameNode, PlainNodeList, SeparatedNodeList};
use PhpSyntax\Token;


/**
 * `use` of traits with optional adaptations in braces.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class TraitUseNode extends MemberNode
{
	public const Slots = ['useKeyword', 'traits', 'semicolon', 'openBrace', 'adaptations', 'closeBrace'];

	public Token $useKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<NameNode> */
	public SeparatedNodeList $traits { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $semicolon = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $openBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var ?PlainNodeList<TraitAdaptationNode> */
	public ?PlainNodeList $adaptations = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $closeBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param SeparatedNodeList<NameNode> $traits
	 * @param ?PlainNodeList<TraitAdaptationNode> $adaptations
	 */
	public function __construct(
		Token $useKeyword,
		SeparatedNodeList $traits,
		?Token $semicolon,
		?Token $openBrace,
		?PlainNodeList $adaptations,
		?Token $closeBrace,
	) {
		$this->useKeyword = $useKeyword;
		$this->traits = $traits;
		$semicolon === null || $this->semicolon = $semicolon;
		$openBrace === null || $this->openBrace = $openBrace;
		$adaptations === null || $this->adaptations = $adaptations;
		$closeBrace === null || $this->closeBrace = $closeBrace;
	}
}
