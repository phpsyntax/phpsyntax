<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;


/**
 * Prints a tree back to source code: every token with its trivia, in the order of the children.
 */
final class Printer
{
	public static function print(Node|Token $node): string
	{
		if ($node instanceof Token) {
			return (string) $node;
		}

		$code = '';
		foreach ($node->getChildren() as $child) {
			$code .= self::print($child);
		}

		return $code;
	}
}
