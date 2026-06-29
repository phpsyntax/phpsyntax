<?php declare(strict_types=1);

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
