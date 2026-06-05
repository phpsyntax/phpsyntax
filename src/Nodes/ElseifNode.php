<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * `elseif` branch, in either syntax.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class ElseifNode extends Node
{
	public const Slots = ['elseifKeyword', 'openParen', 'condition', 'closeParen', 'body', 'colon', 'statements'];

	public Token $elseifKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $condition { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?StatementNode $body = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $colon = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var ?PlainNodeList<StatementNode> */
	public ?PlainNodeList $statements = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param ?PlainNodeList<StatementNode> $statements
	 */
	public function __construct(
		Token $elseifKeyword,
		Token $openParen,
		ExpressionNode $condition,
		Token $closeParen,
		?StatementNode $body,
		?Token $colon,
		?PlainNodeList $statements,
	) {
		$this->elseifKeyword = $elseifKeyword;
		$this->openParen = $openParen;
		$this->condition = $condition;
		$this->closeParen = $closeParen;
		$body === null || $this->body = $body;
		$colon === null || $this->colon = $colon;
		$statements === null || $this->statements = $statements;
	}
}
