<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{ExpressionNode, StatementNode};
use PhpSyntax\Token;


/**
 * `continue` with an optional level.
 */
final class ContinueNode extends StatementNode
{
	public const Slots = ['continueKeyword', 'expression', 'semicolon'];


	/** @internal */
	public function __construct(
		public Token $continueKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
