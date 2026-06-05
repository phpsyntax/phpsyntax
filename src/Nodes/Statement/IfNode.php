<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{ElseifNode, ElseNode, ExpressionNode, PlainNodeList, StatementNode};
use PhpSyntax\Token;


/**
 * `if` statement in either syntax; the body is a statement, the alternative syntax fills statements.
 */
final class IfNode extends StatementNode
{
	public const Slots = ['ifKeyword', 'openParen', 'condition', 'closeParen', 'body', 'colon', 'statements', 'elseifs', 'else', 'endKeyword', 'semicolon'];

	public Token $ifKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $condition { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?StatementNode $body = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $colon = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var ?PlainNodeList<StatementNode> */
	public ?PlainNodeList $statements = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var PlainNodeList<ElseifNode> */
	public PlainNodeList $elseifs { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ElseNode $else = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $endKeyword = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $semicolon = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param ?PlainNodeList<StatementNode> $statements
	 * @param PlainNodeList<ElseifNode> $elseifs
	 */
	public function __construct(
		Token $ifKeyword,
		Token $openParen,
		ExpressionNode $condition,
		Token $closeParen,
		?StatementNode $body,
		?Token $colon,
		?PlainNodeList $statements,
		PlainNodeList $elseifs,
		?ElseNode $else,
		?Token $endKeyword,
		?Token $semicolon,
	) {
		$this->ifKeyword = $ifKeyword;
		$this->openParen = $openParen;
		$this->condition = $condition;
		$this->closeParen = $closeParen;
		$body === null || $this->body = $body;
		$colon === null || $this->colon = $colon;
		$statements === null || $this->statements = $statements;
		$this->elseifs = $elseifs;
		$else === null || $this->else = $else;
		$endKeyword === null || $this->endKeyword = $endKeyword;
		$semicolon === null || $this->semicolon = $semicolon;
	}
}
