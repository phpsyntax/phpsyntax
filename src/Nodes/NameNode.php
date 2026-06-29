<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Lexer\Lexer;
use PhpSyntax\NameKind;
use PhpSyntax\Node;
use PhpSyntax\SymbolKind;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;
use function count, in_array, strlen;


/**
 * Name of a class, function, constant or namespace: one token of any kind, including keywords the grammar accepts as names (static, array, readonly).
 */
final class NameNode extends Node
{
	public const Slots = ['token'];

	private const NameKinds = [TokenKind::Identifier, TokenKind::NameQualified, TokenKind::NameFullyQualified, TokenKind::NameRelative];

	/**
	 * The name as it is written, the leading backslash and the "namespace" prefix included. Writing it replaces
	 * the token with the one the name is written as, so that a qualified name does not stay an identifier; the
	 * trivia around it and its place in the original file stay with it.
	 */
	public string $text {
		get => $this->token->text;
		set {
			$old = $this->token;
			$token = new Token(self::tokenize($value), $value, $old->originalOffset, $old->originalLine);
			$token->setLeadingTrivia($old->leadingTrivia);
			$token->setTrailingTrivia($old->trailingTrivia);
			$this->token = $token;
		}
	}

	public NameKind $kind {
		get => match ($this->token->kind) {
			TokenKind::NameFullyQualified => NameKind::FullyQualified,
			TokenKind::NameQualified => NameKind::Qualified,
			TokenKind::NameRelative => NameKind::Relative,
			default => NameKind::Unqualified,
		};
	}

	/**
	 * Segments of the name without the leading backslash or the "namespace" prefix.
	 * @var list<string>
	 */
	public array $parts {
		get {
			$name = match ($this->kind) {
				NameKind::FullyQualified => substr($this->token->text, 1),
				NameKind::Relative => substr($this->token->text, strlen('namespace\\')),
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

	/**
	 * Which table of names the name belongs to: functions when called, constants when fetched, what a use item
	 * imports, otherwise classes. It follows the place in the tree, so moving the node changes it.
	 */
	public SymbolKind $role {
		get {
			$parent = $this->parent;
			return match (true) {
				$parent instanceof UseItemNode => $parent->kind,
				$parent instanceof Expression\FunctionCallNode && $parent->name === $this => SymbolKind::Function,
				$parent instanceof Expression\ConstantFetchNode => SymbolKind::Constant,
				default => SymbolKind::ClassLike,
			};
		}
	}


	/** @internal */
	public function __construct(
		public Token $token { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/** A name written as the text, in the token the name is written as (Foo, A\B, \A\B, namespace\B). */
	public static function fromText(string $text): self
	{
		return new self(new Token(self::tokenize($text), $text));
	}


	/** Whether the name is a keyword the grammar accepts in place of a name (static, array, readonly, exit...). */
	public function isKeyword(): bool
	{
		return !in_array($this->token->kind, self::NameKinds, strict: true);
	}


	public function isFullyQualified(): bool
	{
		return $this->kind === NameKind::FullyQualified;
	}


	public function isUnqualified(): bool
	{
		return $this->kind === NameKind::Unqualified;
	}


	/** Whether the name is self, static or parent, which stand for a class only where they are written. */
	public function isSpecialClass(): bool
	{
		return $this->kind === NameKind::Unqualified
			&& in_array(strtolower($this->token->text), ['self', 'static', 'parent'], strict: true);
	}


	/** Whether the name declares or imports a symbol instead of referring to one: a namespace statement or a use. */
	public function isDeclaration(): bool
	{
		return $this->parent instanceof UseItemNode
			|| $this->parent instanceof Statement\UseNode
			|| $this->parent instanceof Statement\NamespaceNode;
	}


	/**
	 * Whether the name is written the same, letter case aside where PHP ignores it: a constant is compared
	 * exactly, a class, a function and a namespace are not. The leading backslash is part of the writing
	 * and so part of the comparison; whether two names mean the same class is what NameResolver answers.
	 */
	public function equals(string $name): bool
	{
		return $this->role === SymbolKind::Constant
			? $this->token->text === $name
			: strcasecmp($this->token->text, $name) === 0;
	}


	/**
	 * The kind of token a name is written as: an identifier, a qualified, fully qualified or relative name,
	 * or a keyword. The name must be the whole token, so that no whitespace and no comment ends up in the
	 * text of a token, where the printer would write it out as it stands.
	 */
	private static function tokenize(string $name): int
	{
		$tokens = new Lexer([])->tokenize("<?php $name;", withPositions: false);
		if (
			count($tokens) !== 3 // the name, the semicolon and the end of file
			|| !$tokens[1]->is(';')
			|| $tokens[0]->text !== $name
		) {
			throw new \InvalidArgumentException("'$name' is not a name.");
		}

		return $tokens[0]->kind;
	}
}
