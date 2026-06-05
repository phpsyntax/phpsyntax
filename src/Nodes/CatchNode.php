<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\Statement\BlockNode;


/**
 * `catch` clause with one or more types and an optional variable.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class CatchNode extends Node
{
	public const Slots = ['catchKeyword', 'openParen', 'types', 'variable', 'closeParen', 'body'];

	public Token $catchKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<NameNode> */
	public SeparatedNodeList $types { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?VariableNode $variable = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public BlockNode $body { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param SeparatedNodeList<NameNode> $types
	 */
	public function __construct(
		Token $catchKeyword,
		Token $openParen,
		SeparatedNodeList $types,
		?VariableNode $variable,
		Token $closeParen,
		BlockNode $body,
	) {
		$this->catchKeyword = $catchKeyword;
		$this->openParen = $openParen;
		$this->types = $types;
		$variable === null || $this->variable = $variable;
		$this->closeParen = $closeParen;
		$this->body = $body;
	}
}
