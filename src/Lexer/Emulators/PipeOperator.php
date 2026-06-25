<?php declare(strict_types=1);

/**
 * Ported from nikic/php-parser (BSD-3-Clause, https://github.com/nikic/PHP-Parser).
 */

namespace PhpSyntax\Lexer\Emulators;

use PhpSyntax\Lexer\Emulator;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;
use function count, ord;


/**
 * The |> operator (PHP 8.5).
 */
final class PipeOperator implements Emulator
{
	public function isNeeded(string $code): bool
	{
		return str_contains($code, '|>');
	}


	public function emulate(array $tokens): array
	{
		for ($i = 0, $count = count($tokens) - 1; $i < $count; $i++) {
			$token = $tokens[$i];
			if ($token->kind === ord('|') && $tokens[$i + 1]->kind === ord('>')) {
				$merged = new Token(TokenKind::Pipe, '|>', $token->originalOffset, $token->originalLine);
				array_splice($tokens, $i, 2, [$merged]);
				$count--;
			}
		}

		return $tokens;
	}
}
