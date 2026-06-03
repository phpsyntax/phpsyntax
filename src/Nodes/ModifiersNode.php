<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token, TokenKind, Trivia, TriviaKind, Visibility};
use function count;


/**
 * Modifier keywords in source order (`public`, `static`, `readonly`, `abstract`, `final`, `var`, `public(set)`...); may be empty.
 * @implements \IteratorAggregate<int, Token>
 */
final class ModifiersNode extends Node implements \Countable, \IteratorAggregate
{
	/**
	 * The visibility the modifiers declare, null when they declare none, which leaves a member public,
	 * a promoted parameter too.
	 */
	public ?Visibility $visibility {
		get => match (true) {
			$this->has(TokenKind::Public), $this->has(TokenKind::Var) => Visibility::Public,
			$this->has(TokenKind::Protected) => Visibility::Protected,
			$this->has(TokenKind::Private) => Visibility::Private,
			default => null,
		};
	}

	/** The visibility for writing, which asymmetric visibility declares apart: `public(set)` and its kin. */
	public ?Visibility $writeVisibility {
		get => match (true) {
			$this->has(TokenKind::PublicSet) => Visibility::Public,
			$this->has(TokenKind::ProtectedSet) => Visibility::Protected,
			$this->has(TokenKind::PrivateSet) => Visibility::Private,
			default => null,
		};
	}


	/** @internal */
	public function __construct(
		/** @var list<Token> */
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
		return array_find($this->tokens, fn(Token $token) => $token->kind === $kind);
	}


	public function isEmpty(): bool
	{
		return $this->tokens === [];
	}


	public function has(int $kind): bool
	{
		return $this->findToken($kind) !== null;
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
			$token->setTrailingTrivia([new Trivia(TriviaKind::Whitespace, ' ')]);
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
		$this->tokens = self::spliceList($this->tokens, $index, 1);
		$this->structureChanged();

		$leading = $token->leadingTrivia;
		$trailing = $token->trailingTrivia;
		if (array_any($trailing, fn(Trivia $trivia) => $trivia->kind !== TriviaKind::Whitespace)) {
			if ($previous && !array_any($previous->trailingTrivia, fn(Trivia $trivia) => $trivia->kind !== TriviaKind::Whitespace)) {
				$previous->setTrailingTrivia($trailing);
			} else {
				$leading = [...$leading, ...array_slice($trailing, $trailing[0]->kind === TriviaKind::Whitespace ? 1 : 0)];
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
				if ($token = $child instanceof Token ? $child : $child->getFirstToken()) {
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
