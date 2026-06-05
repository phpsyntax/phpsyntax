<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;


/**
 * Skipped item of a destructuring list (`[, $b] = $x`); has no tokens.
 */
final class EmptyArrayItemNode extends Node
{
	public const Slots = [];


	/** @internal */
	public function __construct()
	{
	}
}
