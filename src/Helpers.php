<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;

use function strlen;


/**
 * Small operations that belong to no class of their own.
 * @internal
 */
final class Helpers
{
	private const Sequences = ['\\\\' => '\\', '\$' => '$', '\n' => "\n", '\r' => "\r", '\t' => "\t", '\v' => "\v", '\e' => "\x1B", '\f' => "\f"];


	/**
	 * Resolves the escape sequences of a double-quoted string; `$quote` is the delimiter the syntax escapes
	 * (`'"'` or `` '`' ``), null in a heredoc, which escapes none. Which sequences a piece of source uses is
	 * a property of the string it stands in, so the delimiter is an argument and the literal nodes know it.
	 */
	public static function unescapeString(string $text, ?string $quote): string
	{
		$sequences = self::Sequences + ($quote === null ? [] : ['\\' . $quote => $quote]);
		return (string) preg_replace_callback(
			'~\\\(?:x[0-9A-Fa-f]{1,2}|u\{[0-9A-Fa-f]+\}|[0-7]{1,3}|.)~s',
			function (array $m) use ($sequences): string {
				$sequence = $m[0];
				return match (true) {
					isset($sequences[$sequence]) => $sequences[$sequence],
					$sequence[1] === 'x' && isset($sequence[2]) => chr((int) hexdec(substr($sequence, 2)) & 0xFF),
					$sequence[1] === 'u' && ($sequence[2] ?? '') === '{' => self::utf8((int) hexdec(substr($sequence, 3, -1))),
					$sequence[1] >= '0' && $sequence[1] <= '7' => chr((int) octdec(substr($sequence, 1)) & 0xFF),
					default => $sequence, // not an escape sequence: the backslash stands for itself
				};
			},
			$text,
		);
	}


	/** Escapes what a double-quoted string cannot hold as it is; `$quote` is the delimiter, null in a heredoc. */
	public static function escapeString(string $value, ?string $quote): string
	{
		$sequences = ['\\' => '\\\\', '$' => '\$', "\n" => '\n', "\r" => '\r', "\t" => '\t', "\v" => '\v', "\x1B" => '\e', "\f" => '\f']
			+ ($quote === null ? [] : [$quote => '\\' . $quote]);
		return (string) preg_replace_callback(
			'~[\\\$\x00-\x1F' . ($quote === null ? '' : preg_quote($quote, '~')) . ']~',
			fn(array $m) => $sequences[$m[0]] ?? sprintf('\x%02X', ord($m[0])),
			$value,
		);
	}


	/** The code point in UTF-8, as `"\u{...}"` writes it. */
	private static function utf8(int $code): string
	{
		return match (true) {
			$code < 0x80 => chr($code & 0x7F),
			$code < 0x800 => chr(0xC0 | $code >> 6 & 0x1F) . chr(0x80 | $code & 0x3F),
			$code < 0x10000 => chr(0xE0 | $code >> 12 & 0x0F) . chr(0x80 | $code >> 6 & 0x3F) . chr(0x80 | $code & 0x3F),
			default => chr(0xF0 | $code >> 18 & 0x07) . chr(0x80 | $code >> 12 & 0x3F) . chr(0x80 | $code >> 6 & 0x3F) . chr(0x80 | $code & 0x3F),
		};
	}


	/**
	 * Returns the list with `$remove` items at `$index` replaced by `$insert`.
	 * @template U
	 * @param  list<U>  $list
	 * @param  list<U>  $insert
	 * @return list<U>
	 */
	public static function spliceList(array $list, int $index, int $remove, array $insert = []): array
	{
		return [...array_slice($list, 0, $index), ...$insert, ...array_slice($list, $index + $remove)];
	}


	/**
	 * The code as a code span of Markdown, as a message writes it, in a run of backticks longer than any it holds;
	 * an empty one, which Markdown cannot mark, as the empty string of PHP.
	 */
	public static function formatCode(string $code): string
	{
		if ($code === '') {
			return "`''`";
		}

		preg_match_all('~`+~', $code, $m);
		$fence = str_repeat('`', max([0, ...array_map(strlen(...), $m[0])]) + 1);
		$pad = str_starts_with($code, '`') || str_ends_with($code, '`') ? ' ' : '';
		return $fence . $pad . $code . $pad . $fence;
	}


	/** Refuses a text that is not whitespace within a line, spaces and tabs; an empty one passes. */
	public static function checkWhitespace(string $text): void
	{
		if (strspn($text, " \t") !== strlen($text)) {
			throw new \InvalidArgumentException(self::formatCode(addcslashes($text, "\0..\37")) . ' is not whitespace within a line, which is made of spaces and tabs.');
		}
	}


	/** Refuses a text that is no name of a variable, written without its dollar sign. */
	public static function checkVariableName(string $name): void
	{
		if (preg_match('~^[a-zA-Z_\x80-\xFF][a-zA-Z0-9_\x80-\xFF]*$~D', $name) !== 1) {
			throw new \InvalidArgumentException(self::formatCode($name) . ' is not the name of a variable.');
		}
	}


	public static function checkLineEnding(string $lineEnding): void
	{
		if ($lineEnding !== "\n" && $lineEnding !== "\r\n" && $lineEnding !== "\r") {
			throw new \InvalidArgumentException(self::formatCode(addcslashes($lineEnding, "\0..\37")) . ' is not a line ending, which is `\n`, `\r\n` or `\r`.');
		}
	}
}
