<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{CaseNode, ExpressionNode, PlainNodeList, StatementNode};
use PhpSyntax\Token;


/**
 * `switch` statement in either syntax; a semicolon may precede the first `case`.
 */
final class SwitchNode extends StatementNode
{
	public const Slots = [
		'switchKeyword', 'openParen', 'subject', 'closeParen', 'openBrace', 'colon', 'leadingSemicolon', 'cases', 'closeBrace',
		'endKeyword', 'semicolon',
	];

	public Token $switchKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $subject { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $openBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $colon = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $leadingSemicolon = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var PlainNodeList<CaseNode> */
	public PlainNodeList $cases { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $closeBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $endKeyword = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $semicolon = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param PlainNodeList<CaseNode> $cases
	 */
	public function __construct(
		Token $switchKeyword,
		Token $openParen,
		ExpressionNode $subject,
		Token $closeParen,
		?Token $openBrace,
		?Token $colon,
		?Token $leadingSemicolon,
		PlainNodeList $cases,
		?Token $closeBrace,
		?Token $endKeyword,
		?Token $semicolon,
	) {
		$this->switchKeyword = $switchKeyword;
		$this->openParen = $openParen;
		$this->subject = $subject;
		$this->closeParen = $closeParen;
		$openBrace === null || $this->openBrace = $openBrace;
		$colon === null || $this->colon = $colon;
		$leadingSemicolon === null || $this->leadingSemicolon = $leadingSemicolon;
		$this->cases = $cases;
		$closeBrace === null || $this->closeBrace = $closeBrace;
		$endKeyword === null || $this->endKeyword = $endKeyword;
		$semicolon === null || $this->semicolon = $semicolon;
	}
}
