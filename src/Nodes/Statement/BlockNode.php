<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{PlainNodeList, StatementNode};
use PhpSyntax\{Surgery, Token};


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
		return self::findLastStatement($this->statements)?->interruptsFlow() ?? false;
	}


	/** Whether the code never goes on past the last statement of the block; false for an empty block. */
	public function alwaysLeaves(): bool
	{
		return self::findLastStatement($this->statements)?->alwaysLeaves() ?? false;
	}


	/**
	 * Moves the statements of the block into the list the block stands in, in its place, and removes the braces the
	 * way `remove()` removes a node: a comment on the opening brace goes before the first statement, one on the closing
	 * brace after the last one, before what follows the block, both on lines of their own where the brace stood on one.
	 * The statements keep their trivia, the indentation included, which `Indentation::shift()` moves a level up.
	 * @throws \LogicException  for a block whose braces stand next to a close tag or inline HTML, where the whitespace
	 *   is output of the script, before anything moves
	 */
	public function unwrap(): void
	{
		$list = $this->parent;
		if (!$list instanceof PlainNodeList) {
			throw new \LogicException('Only a block standing among statements can be unwrapped; a body is written by its setter.');
		}

		foreach ([$this->openBrace, $this->closeBrace] as $brace) {
			$previous = $brace->getPrevious();
			if (($previous && Surgery::opensOutput($previous)) || $brace->getNext()?->is(Token::CloseTag)) {
				throw new \LogicException('The block stands next to a close tag or inline HTML, where the whitespace its braces leave would be output of the script.');
			}
		}

		$after = $this->closeBrace->getNext();
		Surgery::moveCommentsToNext($this->closeBrace);
		$index = $list->indexOf($this);
		foreach ($this->statements->getItems() as $i => $statement) {
			$this->statements->removeItem($statement);
			$list->insert($index + 1 + $i, $statement);
		}

		$this->remove();
		Surgery::splitSeam($after?->getPrevious(), $after); // the comments of the closing brace now follow the last statement
	}
}
