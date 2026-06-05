<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{IdentifierNode, StatementNode};
use PhpSyntax\Token;


/**
 * Label for `goto`.
 */
final class LabelNode extends StatementNode
{
	public const Slots = ['name', 'colon'];


	/** @internal */
	public function __construct(
		public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
