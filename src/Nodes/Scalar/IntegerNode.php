<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Scalar;

use PhpSyntax\Nodes\ScalarNode;
use PhpSyntax\Token;
use function strlen;


/**
 * Integer literal in any base, kept as written.
 */
final class IntegerNode extends ScalarNode
{
	public const Slots = ['token'];

	/** The base the literal is written in: 2, 8, 10 or 16; a leading zero alone is the old octal notation. */
	public int $base {
		get {
			$text = $this->token->text;
			return $text[0] !== '0' || strlen($text) === 1 ? 10 : match (strtolower($text[1])) {
				'x' => 16,
				'b' => 2,
				'o' => 8,
				default => 8,
			};
		}
	}

	/** The value of the literal; a literal beyond the integer range is a float to PHP, so it is a FloatNode. */
	public int $value {
		get {
			$digits = str_replace('_', '', $this->token->text);
			$base = $this->base;
			$second = $digits[1] ?? '';
			$prefixed = $base !== 10 && ($second < '0' || $second > '9'); // 0x, 0b and 0o, unlike the old 017
			$digits = $prefixed ? substr($digits, 2) : $digits;
			return (int) match ($base) {
				16 => hexdec($digits),
				2 => bindec($digits),
				8 => octdec($digits),
				default => $digits,
			};
		}
	}


	/** @internal */
	public function __construct(
		public Token $token { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
