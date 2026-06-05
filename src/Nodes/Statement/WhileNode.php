<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{ExpressionNode, PlainNodeList, StatementNode};
use PhpSyntax\Token;


/**
 * `while` loop in either syntax.
 */
final class WhileNode extends StatementNode
{
	public const Slots = ['whileKeyword', 'openParen', 'condition', 'closeParen', 'body', 'colon', 'statements', 'endKeyword', 'semicolon'];

	public Token $whileKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $condition { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?StatementNode $body = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $colon = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var ?PlainNodeList<StatementNode> */
	public ?PlainNodeList $statements = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $endKeyword = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $semicolon = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param ?PlainNodeList<StatementNode> $statements
	 */
	public function __construct(
		Token $whileKeyword,
		Token $openParen,
		ExpressionNode $condition,
		Token $closeParen,
		?StatementNode $body,
		?Token $colon,
		?PlainNodeList $statements,
		?Token $endKeyword,
		?Token $semicolon,
	) {
		$this->whileKeyword = $whileKeyword;
		$this->openParen = $openParen;
		$this->condition = $condition;
		$this->closeParen = $closeParen;
		$body === null || $this->body = $body;
		$colon === null || $this->colon = $colon;
		$statements === null || $this->statements = $statements;
		$endKeyword === null || $this->endKeyword = $endKeyword;
		$semicolon === null || $this->semicolon = $semicolon;
	}
}
