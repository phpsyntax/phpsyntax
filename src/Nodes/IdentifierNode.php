<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Helpers, Node, SymbolKind, Token};


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
	 * The identifier as it is written; whether its letter case matters is up to what it names, which `equals()`
	 * knows. Writing it takes an identifier and nothing else, so that no whitespace ends up in the text of a token.
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


	/**
	 * Whether the identifier is written the same, letter case aside where PHP ignores it, which it does for what
	 * names a function, a method, a class and their kin, and for `class` after `::`; a property, a constant,
	 * an enum case, a label and a named argument are compared exactly, and so is an identifier standing nowhere.
	 */
	public function equals(string $name): bool
	{
		return $this->isCaseInsensitive()
			? strcasecmp($this->token->text, $name) === 0
			: $this->token->text === $name;
	}


	private function isCaseInsensitive(): bool
	{
		$parent = $this->parent;
		return match (true) {
			$parent instanceof ClassLikeNode,
			$parent instanceof Statement\FunctionNode,
			$parent instanceof Member\MethodNode,
			$parent instanceof Member\PropertyHookNode,
			$parent instanceof Member\TraitPrecedenceNode,
			$parent instanceof Member\TraitAliasNode,
			$parent instanceof Expression\MethodCallNode,
			$parent instanceof Expression\StaticMethodCallNode,
			$parent instanceof DeclareItemNode => true,
			$parent instanceof UseItemNode => $parent->symbolKind !== SymbolKind::Constant,
			$parent instanceof Expression\ClassConstantFetchNode => strcasecmp($this->token->text, 'class') === 0,
			default => false,
		};
	}


	private static function checkText(string $text): void
	{
		if (preg_match('~^[a-zA-Z_\x80-\xFF][a-zA-Z0-9_\x80-\xFF]*$~D', $text) !== 1) {
			throw new \InvalidArgumentException(Helpers::formatCode($text) . ' is not an identifier.');
		}
	}
}
