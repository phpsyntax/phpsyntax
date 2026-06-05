<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{IdentifierNode, StatementNode};
use PhpSyntax\Token;


/**
 * `goto` statement.
 */
final class GotoNode extends StatementNode
{
	public const Slots = ['gotoKeyword', 'label', 'semicolon'];


	/** @internal */
	public function __construct(
		public Token $gotoKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public IdentifierNode $label { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
