<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Scalar;

use PhpSyntax\Nodes\ScalarNode;
use PhpSyntax\Token;
use function intval, ord, strlen;


/**
 * Literal PHP reads as a float, kept as written. That is not the same as float syntax: an integer literal
 * beyond the integer range is a float to PHP whatever its base (0xFFFFFFFFFFFFFFFF as much as
 * 9223372036854775808), and the lexer hands it over as one, so such a literal is a node of this class.
 */
final class FloatNode extends ScalarNode
{
	public const Slots = ['token'];

	/** The value of the literal, which is also how an integer literal beyond the integer range reaches PHP. */
	public float $value {
		get {
			$text = str_replace('_', '', $this->token->text);
			if ($text[0] !== '0' || strlen($text) === 1) { // a plain decimal float, the common case
				return (float) $text;
			}

			return match (strtolower($text[1])) {
				'x' => self::fromDigits(substr($text, 2), 16),
				'b' => self::fromDigits(substr($text, 2), 2),
				'o' => self::fromDigits(substr($text, 2), 8),
				'0', '1', '2', '3', '4', '5', '6', '7' => strspn($text, '01234567') === strlen($text) ? self::fromDigits($text, 8) : (float) $text,
				default => (float) $text, // 0.5 and 0e3 are decimal too
			};
		}
	}


	/** @internal */
	public function __construct(
		public Token $token { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/**
	 * The digits as the lexer of PHP reads them: a double from the first digit on, and outside base 16 the
	 * digit added as its character code and taken off afterwards, which rounds once more. hexdec() and its
	 * kin count differently and land one unit in the last place away.
	 */
	private static function fromDigits(string $digits, int $base): float
	{
		$value = 0.0;
		foreach (str_split($digits) as $digit) {
			$value = $base === 16
				? $value * 16 + intval($digit, 16)
				: $value * $base + ord($digit) - 48;
		}

		return $value;
	}
}
