<?php declare(strict_types=1);

namespace PhpSyntax;

use function chr, ord, sprintf;


/**
 * The escape sequences of a PHP string: what they mean and how a value is written with them. Which of them
 * a piece of source uses is a property of the string it stands in, so the delimiter is an argument here and
 * the literal nodes are what knows it.
 * @internal
 */
final class Escaping
{
	private const Sequences = ['\\\\' => '\\', '\$' => '$', '\n' => "\n", '\r' => "\r", '\t' => "\t", '\v' => "\v", '\e' => "\x1B", '\f' => "\f"];


	/**
	 * Resolves the escape sequences of a double-quoted string; $quote is the delimiter the syntax escapes
	 * ('"' or '`'), null in a heredoc, which escapes none.
	 */
	public static function decode(string $text, ?string $quote): string
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


	/** Escapes what a double-quoted string cannot hold as it is; $quote is the delimiter, null in a heredoc. */
	public static function encode(string $value, ?string $quote): string
	{
		$sequences = ['\\' => '\\\\', '$' => '\$', "\n" => '\n', "\r" => '\r', "\t" => '\t', "\v" => '\v', "\x1B" => '\e', "\f" => '\f']
			+ ($quote === null ? [] : [$quote => '\\' . $quote]);
		return (string) preg_replace_callback(
			'~[\\\$\x00-\x1F' . ($quote === null ? '' : preg_quote($quote, '~')) . ']~',
			fn(array $m) => $sequences[$m[0]] ?? sprintf('\x%02X', ord($m[0])),
			$value,
		);
	}


	/** The code point in UTF-8, as "\u{...}" writes it. */
	private static function utf8(int $code): string
	{
		return match (true) {
			$code < 0x80 => chr($code & 0x7F),
			$code < 0x800 => chr(0xC0 | $code >> 6 & 0x1F) . chr(0x80 | $code & 0x3F),
			$code < 0x10000 => chr(0xE0 | $code >> 12 & 0x0F) . chr(0x80 | $code >> 6 & 0x3F) . chr(0x80 | $code & 0x3F),
			default => chr(0xF0 | $code >> 18 & 0x07) . chr(0x80 | $code >> 12 & 0x3F) . chr(0x80 | $code >> 6 & 0x3F) . chr(0x80 | $code & 0x3F),
		};
	}
}
