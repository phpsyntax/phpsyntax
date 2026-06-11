<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{PlainNodeList, StatementNode};
use PhpSyntax\Token;


/**
 * Statements in braces.
 */
final class BlockNode extends StatementNode
{
	public const Slots = ['openBrace', 'statements', 'closeBrace'];

	public Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var PlainNodeList<StatementNode> */
	public PlainNodeList $statements { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param PlainNodeList<StatementNode> $statements
	 */
	public function __construct(Token $openBrace, PlainNodeList $statements, Token $closeBrace)
	{
		$this->openBrace = $openBrace;
		$this->statements = $statements;
		$this->closeBrace = $closeBrace;
	}


	/**
	 * Whether the code does not go on after the last statement of the block, the empty statement a close tag leaves
	 * behind it aside; false for an empty block.
	 */
	public function interruptsFlow(): bool
	{
		$stmts = $this->statements->getItems();
		$last = array_pop($stmts);
		if ($last instanceof EmptyStatementNode && $last->semicolon->is(Token::CloseTag)) {
			$last = array_pop($stmts);
		}

		return $last?->interruptsFlow() ?? false;
	}
}
