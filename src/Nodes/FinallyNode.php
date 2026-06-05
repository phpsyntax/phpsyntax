<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\Statement\BlockNode;


/**
 * `finally` clause.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class FinallyNode extends Node
{
	public const Slots = ['finallyKeyword', 'body'];

	public Token $finallyKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public BlockNode $body { set => $this->prepareSlot(__PROPERTY__, $value); }


	/** @internal */
	public function __construct(Token $finallyKeyword, BlockNode $body)
	{
		$this->finallyKeyword = $finallyKeyword;
		$this->body = $body;
	}
}
