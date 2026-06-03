<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Helpers, Node, Token, Trivia, Visibility};
use function count;


/**
 * Modifier keywords in source order (`public`, `static`, `readonly`, `abstract`, `final`, `var`, `public(set)`...); may be empty.
 * @implements \IteratorAggregate<int, Token>
 */
final class ModifiersNode extends Node implements \Countable, \IteratorAggregate
{
	/** The visibility of the member, `Public` where the modifiers write none; `getVisibilityToken()` tells whether one is written. */
	public Visibility $visibility {
		get => match ($this->getVisibilityToken()?->id) {
			Token::Protected => Visibility::Protected,
			Token::Private => Visibility::Private,
			default => Visibility::Public,
		};
	}

	/**
	 * The visibility for writing as PHP takes it: the one asymmetric visibility declares apart (`public(set)` and its
	 * kin), `Protected` for a public `readonly`, which PHP writes as `protected(set)`, the `$visibility` otherwise. It
	 * reads the modifiers alone, like `$readonly`, so a property of a readonly class is not taken for readonly.
	 */
	public Visibility $writeVisibility {
		get => match (true) {
			$this->has(Token::PublicSet) => Visibility::Public,
			$this->has(Token::ProtectedSet) => Visibility::Protected,
			$this->has(Token::PrivateSet) => Visibility::Private,
			$this->readonly && $this->visibility === Visibility::Public => Visibility::Protected,
			default => $this->visibility,
		};
	}

	public bool $static {
		get => $this->has(Token::Static);
	}

	public bool $abstract {
		get => $this->has(Token::Abstract);
	}

	public bool $final {
		get => $this->has(Token::Final);
	}

	public bool $readonly {
		get => $this->has(Token::Readonly);
	}

	/** @var list<Token> */
	private array $tokens;


	/**
	 * @internal
	 * @param list<Token> $tokens
	 */
	public function __construct(array $tokens = [])
	{
		$this->tokens = $tokens;
		foreach ($tokens as $token) {
			if ($token->parent === null) { // being built, nothing to check
				$token->parent = $this;
			} else {
				$this->adopt($token);
			}
		}
	}


	/** @return list<Token> */
	public function getTokens(): array
	{
		return $this->tokens;
	}


	/** The modifier token of the kind, null when the modifiers do not have it. */
	public function findToken(int $kind): ?Token
	{
		return array_find($this->tokens, fn(Token $token) => $token->id === $kind);
	}


	public function isEmpty(): bool
	{
		return $this->tokens === [];
	}


	public function has(int $kind): bool
	{
		return $this->findToken($kind) !== null;
	}


	/** The `public`, `protected`, `private` or `var` token, null when no visibility is written. */
	public function getVisibilityToken(): ?Token
	{
		return array_find($this->tokens, fn(Token $token) => in_array($token->id, [Token::Public, Token::Protected, Token::Private, Token::Var], true));
	}


	/**
	 * Appends a modifier. The first one opens the declaration, so it takes over the leading trivia of the token
	 * it now stands before, the open tag and the doc comment among them; one without trailing trivia is kept
	 * apart from what follows by a space.
	 */
	public function append(Token $token): void
	{
		$this->prepareValue($token, null);
		$next = $this->findFollowingToken();
		$this->adopt($token);
		$this->tokens[] = $token;
		$this->structureChanged();
		if (count($this->tokens) === 1 && $next?->leadingTrivia) {
			$token->setLeadingTrivia([...$next->leadingTrivia, ...$token->leadingTrivia]);
			$next->setLeadingTrivia([]);
		}

		if (!$token->trailingTrivia) {
			$token->setTrailingTrivia([Trivia::fromText(' ')]);
		}
	}


	/**
	 * Removes a modifier. Its leading trivia go to the token after it, which may now open the declaration, and
	 * a comment after it stays: after the modifier before it, or on a line of its own above the token after it.
	 */
	public function removeToken(Token $token): void
	{
		$index = array_search($token, $this->tokens, strict: true);
		if ($index === false) {
			throw self::describeChildMismatch($token);
		}

		$previous = $this->tokens[$index - 1] ?? null;
		$next = $this->tokens[$index + 1] ?? $this->findFollowingToken();
		$this->release($token);
		$this->tokens = Helpers::spliceList($this->tokens, $index, 1);
		$this->structureChanged();

		$leading = $token->leadingTrivia;
		$trailing = $token->trailingTrivia;
		if (array_any($trailing, fn(Trivia $trivia) => !$trivia->is(Trivia::Whitespace))) {
			if ($previous && !array_any($previous->trailingTrivia, fn(Trivia $trivia) => !$trivia->is(Trivia::Whitespace))) {
				$previous->setTrailingTrivia($trailing);
			} else {
				$leading = [...$leading, ...array_slice($trailing, $trailing[0]->is(Trivia::Whitespace) ? 1 : 0)];
			}
		}

		$token->setLeadingTrivia([]);
		$token->setTrailingTrivia([]);
		if ($next && $leading) {
			$next->setLeadingTrivia([...$leading, ...$next->leadingTrivia]);
		}
	}


	/** The first token after the modifiers, the one a modifier appended to them stands before. */
	private function findFollowingToken(): ?Token
	{
		for ($node = $this; $node->parent !== null; $node = $node->parent) {
			$children = $node->parent->getChildren();
			foreach (array_slice($children, (int) array_search($node, $children, strict: true) + 1) as $child) {
				if ($token = $child->getFirstToken()) {
					return $token;
				}
			}
		}

		return null;
	}


	public function getChildren(): array
	{
		return $this->tokens;
	}


	public function getFirstToken(): ?Token
	{
		return $this->tokens[0] ?? null;
	}


	public function getLastToken(): ?Token
	{
		return $this->tokens[count($this->tokens) - 1] ?? null;
	}


	public function findSlotOf(Node|Token $child): ?string
	{
		return in_array($child, $this->tokens, true) ? 'tokens' : null;
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
		$this->tokens = Helpers::spliceList($this->tokens, $index, 1, [$new]);
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
