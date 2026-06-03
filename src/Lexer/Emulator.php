<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Lexer;

use PhpSyntax\Token;


/**
 * Rewrites the raw token stream so that syntax of a newer PHP version is tokenized as that version would.
 * Raw tokens still contain whitespace and comments as tokens.
 */
interface Emulator
{
	function isNeeded(string $code): bool;

	/**
	 * @param  list<Token>  $tokens
	 * @return list<Token>
	 */
	function emulate(array $tokens): array;
}
