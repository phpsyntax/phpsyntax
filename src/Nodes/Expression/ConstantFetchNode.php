<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ExpressionNode, NameNode};


/**
 * Constant access by name: `FOO`, `\Foo\BAR`.
 */
final class ConstantFetchNode extends ExpressionNode
{
	public const Slots = ['name'];


	/** @internal */
	public function __construct(
		public NameNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
