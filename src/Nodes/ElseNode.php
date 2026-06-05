<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * `else` branch, in either syntax; `else if` is an `else` with an `if` statement as the body.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class ElseNode extends Node
{
	public const Slots = ['elseKeyword', 'body', 'colon', 'statements'];

	public Token $elseKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?StatementNode $body = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $colon = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var ?PlainNodeList<StatementNode> */
	public ?PlainNodeList $statements = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param ?PlainNodeList<StatementNode> $statements
	 */
	public function __construct(Token $elseKeyword, ?StatementNode $body, ?Token $colon, ?PlainNodeList $statements)
	{
		$this->elseKeyword = $elseKeyword;
		$body === null || $this->body = $body;
		$colon === null || $this->colon = $colon;
		$statements === null || $this->statements = $statements;
	}
}
