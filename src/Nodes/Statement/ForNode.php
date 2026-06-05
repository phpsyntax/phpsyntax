<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{ExpressionNode, PlainNodeList, SeparatedNodeList, StatementNode};
use PhpSyntax\Token;


/**
 * `for` loop in either syntax.
 */
final class ForNode extends StatementNode
{
	public const Slots = [
		'forKeyword', 'openParen', 'initializers', 'firstSemicolon', 'conditions', 'secondSemicolon', 'updates', 'closeParen', 'body',
		'colon', 'statements', 'endKeyword', 'semicolon',
	];

	public Token $forKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<ExpressionNode> */
	public SeparatedNodeList $initializers { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $firstSemicolon { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<ExpressionNode> */
	public SeparatedNodeList $conditions { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $secondSemicolon { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<ExpressionNode> */
	public SeparatedNodeList $updates { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?StatementNode $body = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $colon = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var ?PlainNodeList<StatementNode> */
	public ?PlainNodeList $statements = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $endKeyword = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $semicolon = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param SeparatedNodeList<ExpressionNode> $initializers
	 * @param SeparatedNodeList<ExpressionNode> $conditions
	 * @param SeparatedNodeList<ExpressionNode> $updates
	 * @param ?PlainNodeList<StatementNode> $statements
	 */
	public function __construct(
		Token $forKeyword,
		Token $openParen,
		SeparatedNodeList $initializers,
		Token $firstSemicolon,
		SeparatedNodeList $conditions,
		Token $secondSemicolon,
		SeparatedNodeList $updates,
		Token $closeParen,
		?StatementNode $body,
		?Token $colon,
		?PlainNodeList $statements,
		?Token $endKeyword,
		?Token $semicolon,
	) {
		$this->forKeyword = $forKeyword;
		$this->openParen = $openParen;
		$this->initializers = $initializers;
		$this->firstSemicolon = $firstSemicolon;
		$this->conditions = $conditions;
		$this->secondSemicolon = $secondSemicolon;
		$this->updates = $updates;
		$this->closeParen = $closeParen;
		$body === null || $this->body = $body;
		$colon === null || $this->colon = $colon;
		$statements === null || $this->statements = $statements;
		$endKeyword === null || $this->endKeyword = $endKeyword;
		$semicolon === null || $this->semicolon = $semicolon;
	}
}
