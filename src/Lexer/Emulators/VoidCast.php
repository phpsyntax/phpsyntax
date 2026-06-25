<?php declare(strict_types=1);

/**
 * Ported from nikic/php-parser (BSD-3-Clause, https://github.com/nikic/PHP-Parser).
 */

namespace PhpSyntax\Lexer\Emulators;

use PhpSyntax\Lexer\Emulator;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;
use function array_slice, count, ord, strlen;


/**
 * The (void) cast (PHP 8.5).
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

			$merged = new Token(TokenKind::VoidCast, $text, $tokens[$i]->originalOffset, $tokens[$i]->originalLine);
			array_splice($tokens, $i, $length, [$merged]);
			$count -= $length - 1;
		}

		return $tokens;
	}


	/**
	 * Returns the number of tokens forming "(void)" at the index, or null.
	 * @param list<Token> $tokens
	 */
	private static function matchCast(array $tokens, int $index): ?int
	{
		if ($tokens[$index]->kind !== ord('(')) {
			return null;
		}

		$i = $index + 1;
		if (self::isInlineWhitespace($tokens[$i] ?? null)) {
			$i++;
		}

		if (
			!isset($tokens[$i])
			|| $tokens[$i]->kind !== TokenKind::Identifier
			|| strtolower($tokens[$i]->text) !== 'void'
		) {
			return null;
		}

		$i++;
		if (self::isInlineWhitespace($tokens[$i] ?? null)) {
			$i++;
		}

		return isset($tokens[$i]) && $tokens[$i]->kind === ord(')')
			? $i - $index + 1
			: null;
	}


	private static function isInlineWhitespace(?Token $token): bool
	{
		return $token?->kind === TokenKind::Whitespace
			&& strspn($token->text, " \t") === strlen($token->text);
	}
}
