<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;
use PhpSyntax\Visibility;
use function count, in_array;


/**
 * Modifier keywords in source order (public, static, readonly, abstract, final, var, public(set)...); may be empty.
 * @implements \IteratorAggregate<int, Token>
 */
final class ModifiersNode extends Node implements \Countable, \IteratorAggregate
{
	/**
	 * The visibility the modifiers declare, null when they declare none: a member without one is public,
	 * a parameter without one is not promoted.
	 */
	public ?Visibility $visibility {
		get => match (true) {
			$this->has(TokenKind::Public), $this->has(TokenKind::Var) => Visibility::Public,
			$this->has(TokenKind::Protected) => Visibility::Protected,
			$this->has(TokenKind::Private) => Visibility::Private,
			default => null,
		};
	}

	/** The visibility for writing, which asymmetric visibility declares apart: public(set) and its kin. */
	public ?Visibility $writeVisibility {
		get => match (true) {
			$this->has(TokenKind::PublicSet) => Visibility::Public,
			$this->has(TokenKind::ProtectedSet) => Visibility::Protected,
			$this->has(TokenKind::PrivateSet) => Visibility::Private,
			default => null,
		};
	}


	/**
	 * @internal
	 */
	public function __construct(
		/** @var list<Token>  the modifiers; only the node writes them, through its own methods */
		public protected(set) array $tokens = [],
	) {
		foreach ($tokens as $token) {
			$this->adopt($token);
		}
	}


	/** @return list<Token> */
	public function getTokens(): array
	{
		return $this->tokens;
	}


	/** Whether the member is public, which it also is without a visibility of its own. */
	public function isPublic(): bool
	{
		return !$this->has(TokenKind::Private) && !$this->has(TokenKind::Protected);
	}


	public function isProtected(): bool
	{
		return $this->has(TokenKind::Protected);
	}


	public function isPrivate(): bool
	{
		return $this->has(TokenKind::Private);
	}


	public function isStatic(): bool
	{
		return $this->has(TokenKind::Static);
	}


	public function isAbstract(): bool
	{
		return $this->has(TokenKind::Abstract);
	}


	public function isFinal(): bool
	{
		return $this->has(TokenKind::Final);
	}


	public function isReadonly(): bool
	{
		return $this->has(TokenKind::Readonly);
	}


	/** The modifier token of the kind, null when the modifiers do not have it. */
	public function findToken(int $kind): ?Token
	{
		foreach ($this->tokens as $token) {
			if ($token->kind === $kind) {
				return $token;
			}
		}

		return null;
	}


	public function isEmpty(): bool
	{
		return $this->tokens === [];
	}


	public function has(int $kind): bool
	{
		return $this->findToken($kind) !== null;
	}


	public function append(Token $token): void
	{
		$this->prepareValue($token, null);
		$this->adopt($token);
		$this->tokens[] = $token;
		$this->structureChanged();
	}


	public function removeToken(Token $token): void
	{
		$index = array_search($token, $this->tokens, strict: true);
		if ($index === false) {
			throw self::describeChildMismatch($token);
		}

		$this->release($token);
		$this->tokens = self::spliceList($this->tokens, $index, 1);
		$this->structureChanged();
	}


	public function getChildren(): array
	{
		return $this->tokens;
	}


	public function findSlotOf(Node|Token $child): ?string
	{
		return in_array($child, $this->tokens, strict: true) ? 'tokens' : null;
	}


	public function replaceChild(Node|Token $old, Node|Token $new): void
	{
		$index = $old instanceof Token ? array_search($old, $this->tokens, strict: true) : false;
		if ($index === false || !$new instanceof Token) {
			throw self::describeChildMismatch($old);
		}

		$this->prepareValue($new, $old);
		$this->release($old);
		$this->adopt($new);
		$this->tokens = self::spliceList($this->tokens, $index, 1, [$new]);
		$this->structureChanged();
	}


	public function count(): int
	{
		return count($this->tokens);
	}


	/**
	 * The modifier tokens, as a snapshot safe to iterate while mutating them.
	 * @return \ArrayIterator<int, Token>
	 */
	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->tokens);
	}


	public function __clone()
	{
		parent::__clone();
		$this->tokens = $this->cloneChildren($this->tokens);
	}
}
