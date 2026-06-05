<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ExpressionNode, MatchArmNode, SeparatedNodeList};
use PhpSyntax\Token;


/**
 * `match` expression.
 */
final class MatchNode extends ExpressionNode
{
	public const Slots = ['matchKeyword', 'openParen', 'subject', 'closeParen', 'openBrace', 'arms', 'closeBrace'];

	public Token $matchKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $subject { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<MatchArmNode> */
	public SeparatedNodeList $arms { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param SeparatedNodeList<MatchArmNode> $arms
	 */
	public function __construct(
		Token $matchKeyword,
		Token $openParen,
		ExpressionNode $subject,
		Token $closeParen,
		Token $openBrace,
		SeparatedNodeList $arms,
		Token $closeBrace,
	) {
		$this->matchKeyword = $matchKeyword;
		$this->openParen = $openParen;
		$this->subject = $subject;
		$this->closeParen = $closeParen;
		$this->openBrace = $openBrace;
		$this->arms = $arms;
		$this->closeBrace = $closeBrace;
	}
}
