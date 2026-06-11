<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Analyses;

use PhpSyntax\{Node, SymbolKind};


/**
 * A function or a constant the code declares into a namespace, as `NameResolver::findNamespacedDeclarations()` finds it.
 */
final readonly class Declaration
{
	public function __construct(
		public SymbolKind $kind,
		/** fully qualified, without a leading backslash */
		public string $name,
		/** what names it: the name of a function, the item of a `const` statement, the string `define()` is given */
		public Node $node,
	) {
	}
}
