<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\{SeparatedNodeList, StatementNode};
use PhpSyntax\Token;


/**
 * `global` statement.
 */
final class GlobalNode extends StatementNode
{
	public const Slots = ['globalKeyword', 'variables', 'semicolon'];

	public Token $globalKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<VariableNode> */
	public SeparatedNodeList $variables { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param SeparatedNodeList<VariableNode> $variables
	 */
	public function __construct(Token $globalKeyword, SeparatedNodeList $variables, Token $semicolon)
	{
		$this->globalKeyword = $globalKeyword;
		$this->variables = $variables;
		$this->semicolon = $semicolon;
	}
}
