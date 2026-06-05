<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{NodeList, StatementNode};
use PhpSyntax\Token;


/**
 * Statements in braces.
 */
final class BlockNode extends StatementNode
{
	public const Slots = ['openBrace', 'statements', 'closeBrace'];


	/** @internal */
	public function __construct(
		public Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var NodeList<StatementNode> */
		public NodeList $statements { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
