<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Helpers, Node, Token};


/**
 * Identifier of a declaration, member, label, hook, alias or named argument: one token of any kind, including keywords.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class IdentifierNode extends Node
{
	public const Slots = ['token'];

	public Token $token { set => $this->prepareSlot(__PROPERTY__, $value); }

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
	public function __construct(Token $token)
	{
		$this->token = $token;
	}


	/** An identifier written as the text, which takes an identifier and nothing else. */
	public static function fromText(string $text): self
	{
		self::checkText($text);
		return new self(new Token(Token::Identifier, $text));
	}


	private static function checkText(string $text): void
	{
		if (preg_match('~^[a-zA-Z_\x80-\xFF][a-zA-Z0-9_\x80-\xFF]*$~D', $text) !== 1) {
			throw new \InvalidArgumentException(Helpers::formatCode($text) . ' is not an identifier.');
		}
	}
}
