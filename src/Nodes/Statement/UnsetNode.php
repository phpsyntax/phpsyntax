<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{ExpressionNode, SeparatedNodeList, StatementNode};
use PhpSyntax\Token;


/**
 * `unset` statement.
 */
final class UnsetNode extends StatementNode
{
	public const Slots = ['unsetKeyword', 'openParen', 'variables', 'closeParen', 'semicolon'];

	public Token $unsetKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<ExpressionNode> */
	public SeparatedNodeList $variables { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param SeparatedNodeList<ExpressionNode> $variables
	 */
	public function __construct(Token $unsetKeyword, Token $openParen, SeparatedNodeList $variables, Token $closeParen, Token $semicolon)
	{
		$this->unsetKeyword = $unsetKeyword;
		$this->openParen = $openParen;
		$this->variables = $variables;
		$this->closeParen = $closeParen;
		$this->semicolon = $semicolon;
	}
}
