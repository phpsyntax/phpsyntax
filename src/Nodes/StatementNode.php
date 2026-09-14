<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{CommentPolicy, Node, Token};
use PhpSyntax\Nodes\Expression\{ExitNode, ThrowNode};
use PhpSyntax\Nodes\Statement\{BreakNode, ContinueNode, EmptyStatementNode, ExpressionStatementNode, GotoNode, ReturnNode};


/**
 * Statement: what a file, a block and the body of a control structure consist of.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
abstract class StatementNode extends Node
{
	/**
	 * Removes the statement as `Node::remove()` does. A statement ended by a close tag leaves the tag behind as
	 * an empty statement, so that the text after it stays text. A statement that opens its code too, as `<?=`
	 * does, goes whole, and so does the empty statement of a close tag, whose removal is for the caller to decide.
	 */
	public function remove(CommentPolicy $comments = CommentPolicy::MoveToNextToken, bool $mergeBlankLines = false): void
	{
		$tag = $this->getLastToken();
		if (
			$tag->is(Token::CloseTag)
			&& $tag->parent
			&& !$this instanceof EmptyStatementNode
			&& !$this->getFirstToken()->is(Token::OpenTagWithEcho)
			&& $this->parent instanceof PlainNodeList
		) {
			$leading = $tag->leadingTrivia;
			$tag->parent->replaceChild($tag, Token::fromText(';'));
			$this->parent->insert($this->parent->indexOf($this) + 1, new EmptyStatementNode($tag));
			$tag->setLeadingTrivia($leading); // neither the replacement nor the insertion lays the tag out anew
		}

		parent::remove($comments, $mergeBlankLines);
	}


	/**
	 * Whether the code does not go on after the statement: `return`, `break`, `continue`, `goto`, `throw` or `exit`.
	 * It reads the statement itself, so an `if` whose every branch returns is no such statement; that is control flow.
	 */
	public function interruptsFlow(): bool
	{
		return match (true) {
			$this instanceof ReturnNode,
			$this instanceof BreakNode,
			$this instanceof ContinueNode,
			$this instanceof GotoNode => true,
			$this instanceof ExpressionStatementNode => $this->expression instanceof ThrowNode || $this->expression instanceof ExitNode,
			default => false,
		};
	}
}
