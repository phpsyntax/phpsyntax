<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Helpers, Lexer, NameForm, Node, Token};
use function count, in_array, strlen;


/**
 * Name of a class, function, constant or namespace: one token of any kind, including keywords the grammar accepts as names (`static`, `array`, `readonly`).
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class NameNode extends Node
{
	public const Slots = ['token'];

	private const NameKinds = [Token::Identifier, Token::NameQualified, Token::NameFullyQualified, Token::NameRelative];

	public Token $token { set => $this->prepareSlot(__PROPERTY__, $value); }

	/**
	 * The name as it is written, the leading backslash and the `namespace` prefix included. Writing it replaces
	 * the token with the one the name is written as, so that a qualified name does not stay an identifier; the
	 * trivia around it and its place in the original file stay with it.
	 */
	public string $text {
		get => $this->token->text;
		set {
			$old = $this->token;
			$token = new Token(self::tokenize($value), $value, $old->line, $old->pos);
			$token->setLeadingTrivia($old->leadingTrivia);
			$token->setTrailingTrivia($old->trailingTrivia);
			$this->token = $token;
		}
	}

	public NameForm $form {
		get => match ($this->token->id) {
			Token::NameFullyQualified => NameForm::FullyQualified,
			Token::NameQualified => NameForm::Qualified,
			Token::NameRelative => NameForm::Relative,
			default => NameForm::Unqualified,
		};
	}

	/**
	 * Segments of the name without the leading backslash or the `namespace` prefix.
	 * @var list<string>
	 */
	public array $parts {
		get {
			$name = match ($this->form) {
				NameForm::FullyQualified => substr($this->token->text, 1),
				NameForm::Relative => substr($this->token->text, strlen('namespace\\')),
				default => $this->token->text,
			};
			return explode('\\', $name);
		}
	}

	/** The last segment, which is what an import of the name brings in. */
	public string $shortName {
		get {
			$parts = $this->parts;
			return $parts[count($parts) - 1];
		}
	}


	/** @internal */
	public function __construct(Token $token)
	{
		$this->token = $token;
	}


	/**
	 * A name written as the text, in the token the name is written as (`Foo`, `A\B`, `\A\B`, `namespace\B`); a keyword
	 * passes as well, the grammar taking one as a name in some places, whether or not it can name a symbol here.
	 */
	public static function fromText(string $text): self
	{
		return new self(new Token(self::tokenize($text), $text));
	}


	/** Whether the name is a keyword the grammar accepts in place of a name (`static`, `array`, `readonly`, `exit`...). */
	public function isKeyword(): bool
	{
		return !in_array($this->token->id, self::NameKinds, true);
	}


	/** Whether the name is `self`, `static` or `parent`, which stand for a class only where they are written. */
	public function isSpecialClass(): bool
	{
		return $this->form === NameForm::Unqualified
			&& in_array(strtolower($this->token->text), ['self', 'static', 'parent'], true);
	}


	/**
	 * The kind of token a name is written as: an identifier, a qualified, fully qualified or relative name,
	 * or a keyword. The name must be the whole token, so that no whitespace and no comment ends up in the
	 * text of a token, where the printer would write it out as it stands.
	 */
	private static function tokenize(string $name): int
	{
		$token = Lexer::readToken($name);
		if (
			$token === null
			|| (!in_array($token->id, self::NameKinds, true) && !preg_match('~^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$~D', $name)) // a keyword is written as one
		) {
			throw new \InvalidArgumentException(Helpers::formatCode($name) . ' is not a name.');
		}

		return $token->id;
	}
}
