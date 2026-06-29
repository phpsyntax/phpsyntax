<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;


/**
 * Identifier of a member, label, hook or alias: one token of any kind, including reserved words.
 */
final class IdentifierNode extends Node
{
	public const Slots = ['token'];

	/**
	 * The identifier as it is written. Whether its letter case matters is up to what it names, so comparing
	 * it is left to the caller: a member and a label are case-sensitive, a magic method is not. Writing it
	 * takes an identifier and nothing else, so that no whitespace ends up in the text of a token.
	 */
	public string $text {
		get => $this->token->text;
		set {
			self::checkText($value);
			$this->token->setText($value);
		}
	}


	/** @internal */
	public function __construct(
		public Token $token { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/** An identifier written as the text, which takes an identifier and nothing else. */
	public static function fromText(string $text): self
	{
		self::checkText($text);
		return new self(new Token(TokenKind::Identifier, $text));
	}


	private static function checkText(string $text): void
	{
		if (preg_match('~^[a-zA-Z_\x80-\xFF][a-zA-Z0-9_\x80-\xFF]*$~D', $text) !== 1) {
			throw new \InvalidArgumentException("'$text' is not an identifier.");
		}
	}
}
