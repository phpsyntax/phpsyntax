<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{DestructuringNode, ExpressionNode, PlainNodeList, StatementNode};
use PhpSyntax\Token;


/**
 * `foreach` loop in either syntax.
 */
final class ForeachNode extends StatementNode
{
	public const Slots = [
		'foreachKeyword', 'openParen', 'expression', 'asKeyword', 'key', 'doubleArrow', 'ampersand', 'value', 'closeParen', 'body', 'colon',
		'statements', 'endKeyword', 'semicolon',
	];

	public Token $foreachKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $asKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ExpressionNode $key = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $doubleArrow = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $ampersand = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode|DestructuringNode $value { set => $this->prepareSlot(__PROPERTY__, $value); }
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
		Token $foreachKeyword,
		Token $openParen,
		ExpressionNode $expression,
		Token $asKeyword,
		?ExpressionNode $key,
		?Token $doubleArrow,
		?Token $ampersand,
		ExpressionNode|DestructuringNode $value,
		Token $closeParen,
		?StatementNode $body,
		?Token $colon,
		?PlainNodeList $statements,
		?Token $endKeyword,
		?Token $semicolon,
	) {
		$this->foreachKeyword = $foreachKeyword;
		$this->openParen = $openParen;
		$this->expression = $expression;
		$this->asKeyword = $asKeyword;
		$key === null || $this->key = $key;
		$doubleArrow === null || $this->doubleArrow = $doubleArrow;
		$ampersand === null || $this->ampersand = $ampersand;
		$this->value = $value;
		$this->closeParen = $closeParen;
		$body === null || $this->body = $body;
		$colon === null || $this->colon = $colon;
		$statements === null || $this->statements = $statements;
		$endKeyword === null || $this->endKeyword = $endKeyword;
		$semicolon === null || $this->semicolon = $semicolon;
	}
}
