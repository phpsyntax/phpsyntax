<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Scalar;

use PhpSyntax\{Node, Token};


/**
 * Literal text between interpolations, whitespace included. What its escape sequences mean depends on the
 * string it stands in, so the part keeps them as written and whoever reads it resolves them for that
 * string, as `HeredocNode::$value` does for a heredoc.
 */
final class InterpolatedStringPartNode extends Node
{
	public const Slots = ['token'];


	/** @internal */
	public function __construct(
		public Token $token { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
