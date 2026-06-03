<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;


/**
 * Rewrites the output of `Token::tokenize()` so that syntax of a newer PHP version is tokenized as that
 * version would. The tokens carry the ids of the running PHP and still contain whitespace and comments;
 * a token the emulator makes carries its kind, the negative one `Token` gives a token the running PHP lacks.
 * @internal the lexer runs the emulators of the library, an emulator of another hand is not supported
 */
interface Emulator
{
	function isNeeded(string $code): bool;

	/**
	 * @param  array<int, Token>  $tokens
	 * @return array<int, Token>
	 */
	function emulate(array $tokens): array;
}
