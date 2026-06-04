<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * The `...` placeholder of a first-class callable or a partial application: `f(...)`, `f($a, ...)`.
 */
final class VariadicPlaceholderNode extends Node
{
	public const Slots = ['ellipsis'];


	/** @internal */
	public function __construct(
		public Token $ellipsis { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
