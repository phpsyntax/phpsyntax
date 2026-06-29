<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Scalar;

use PhpSyntax\Escaping;
use PhpSyntax\Nodes\ScalarNode;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;


/**
 * String literal without interpolation, quotes included.
 */
final class StringNode extends ScalarNode
{
	public const Slots = ['token'];

	/** The delimiter the literal is written with: ' or ", a b or B prefix left out. */
	public string $quote {
		get => $this->token->text[-1];
	}

	/** The value of the literal with its escape sequences resolved. */
	public string $value {
		get {
			$quote = $this->quote;
			$text = substr($this->token->text, strpos($this->token->text, $quote) + 1, -1);
			return $quote === '"'
				? Escaping::decode($text, quote: '"')
				: str_replace(['\\\\', "\\'"], ['\\', "'"], $text);
		}
	}


	/** @internal */
	public function __construct(
		public Token $token { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/** A literal standing for the value, escaped as the delimiter needs it. */
	public static function fromValue(string $value, string $quote = "'"): self
	{
		if ($quote !== '"' && $quote !== "'") {
			throw new \InvalidArgumentException("A string is written with ' or \", not '$quote'.");
		}

		return new self(new Token(TokenKind::ConstantEncapsedString, $quote . self::writeValue($value, $quote) . $quote));
	}


	/**
	 * Writes the literal: the value escaped as the delimiter needs it, in the delimiter given or in the one
	 * it has. The two go together, because the delimiter decides how the value is written.
	 */
	public function setValue(string $value, ?string $quote = null): void
	{
		$quote ??= $this->quote;
		if ($quote !== '"' && $quote !== "'") {
			throw new \InvalidArgumentException("A string is written with ' or \", not '$quote'.");
		}

		$prefix = substr($this->token->text, 0, strpos($this->token->text, $this->quote) ?: 0);
		$this->token->setText($prefix . $quote . self::writeValue($value, $quote) . $quote);
	}


	/** The value as the given delimiter writes it, escapes and all. */
	private static function writeValue(string $value, string $quote): string
	{
		return $quote === '"'
			? Escaping::encode($value, quote: '"')
			: str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
	}
}
