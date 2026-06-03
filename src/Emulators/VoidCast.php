<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Emulators;

use PhpSyntax\{Emulator, Token};
use function count, ord, strlen;


/**
 * The `(void)` cast (PHP 8.5).
 * Ported from nikic/php-parser (BSD-3-Clause, https://github.com/nikic/PHP-Parser).
 * @internal
 */
final class VoidCast implements Emulator
{
	public function isNeeded(string $code): bool
	{
		return preg_match('~\([ \t]*void[ \t]*\)~i', $code) === 1;
	}


	public function emulate(array $tokens): array
	{
		for ($i = 0, $count = count($tokens); $i < $count; $i++) {
			$length = self::matchCast($tokens, $i);
			if ($length === null) {
				continue;
			}

			$text = '';
			foreach (array_slice($tokens, $i, $length) as $token) {
				$text .= $token->text;
			}

			$merged = new Token(Token::VoidCast, $text, $tokens[$i]->line, $tokens[$i]->pos);
			array_splice($tokens, $i, $length, [$merged]);
			$count -= $length - 1;
		}

		return $tokens;
	}


	/**
	 * Returns the number of tokens forming `(void)` at the index, or null.
	 * @param array<int, Token> $tokens
	 */
	private static function matchCast(array $tokens, int $index): ?int
	{
		if ($tokens[$index]->id !== ord('(')) {
			return null;
		}

		$i = $index + 1;
		if (self::isInlineWhitespace($tokens[$i] ?? null)) {
			$i++;
		}

		if (
			!isset($tokens[$i])
			|| $tokens[$i]->id !== T_STRING
			|| strtolower($tokens[$i]->text) !== 'void'
		) {
			return null;
		}

		$i++;
		if (self::isInlineWhitespace($tokens[$i] ?? null)) {
			$i++;
		}

		return isset($tokens[$i]) && $tokens[$i]->id === ord(')')
			? $i - $index + 1
			: null;
	}


	private static function isInlineWhitespace(?Token $token): bool
	{
		return $token?->id === T_WHITESPACE
			&& strspn($token->text, " \t") === strlen($token->text);
	}
}
