<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;

use PhpSyntax\Nodes\FileNode;
use function count;


/**
 * Node of the concrete syntax tree; every token of the source is reachable through the children. The nodes and
 * the lists are the library's own: a class extending one or implementing an interface of the nodes elsewhere is
 * not supported, so the protected members, the machinery of the writes, are internal.
 */
abstract class Node implements \Stringable
{
	public const Slots = [];

	/** The node this one belongs to; only the tree writes it. */
	public protected(set) ?Node $parent = null;

	/**
	 * The node as it is written, without the trivia on its outer edges, which printing it writes too.
	 */
	public string $text {
		get => Printer::printText($this);
	}

	/**
	 * The trivia before the node, which are the leading trivia of its first token.
	 * @var list<Trivia>
	 */
	public array $leadingTrivia {
		get => $this->getFirstToken()->leadingTrivia ?? [];
	}

	/**
	 * The trivia after the node, which are the trailing trivia of its last token.
	 * @var list<Trivia>
	 */
	public array $trailingTrivia {
		get => $this->getLastToken()->trailingTrivia ?? [];
	}


	/**
	 * Children in source order, without empty slots.
	 * @return list<Node|Token>
	 */
	public function getChildren(): array
	{
		$children = [];
		foreach (static::Slots as $slot) {
			if ($this->$slot !== null) {
				$children[] = $this->$slot;
			}
		}

		return $children;
	}


	/**
	 * Replaces a direct child; the new one must fit the type of the slot.
	 */
	public function replaceChild(self|Token $old, self|Token $new): void
	{
		$slot = $this->findSlotOf($old) ?? throw self::describeChildMismatch($old);
		try {
			$this->$slot = $new;
		} catch (\TypeError) {
			throw new \InvalidArgumentException(self::formatChild($new) . " cannot be placed in the slot `$slot` of `" . static::class . '`.');
		}
	}


	/**
	 * The slot the direct child stands in; null when it is not a child. A list and `ModifiersNode` answer with the
	 * collection their child is in, `items`, `separators` or `tokens`, which is read, not a slot to write.
	 */
	public function findSlotOf(self|Token $child): ?string
	{
		// dresscode:ignore arrayFunctionForForeach -- the loop reads a property of the object by the slot name
		foreach (static::Slots as $slot) {
			if ($this->$slot === $child) {
				return $slot;
			}
		}

		return null;
	}


	/**
	 * Everything a write to a slot does but the write itself, called by the set hook of the slot: the value is
	 * adopted, the current one released and the file told; the index reads the new shape at its next query.
	 * @template T of self|Token|null
	 * @param  T  $value
	 * @return T
	 */
	protected function prepareSlot(string $slot, self|Token|null $value): self|Token|null
	{
		$old = $this->$slot ?? null;
		if ($value !== null && ($value->parent !== null || $this->parent !== null || $value === $this)) {
			$this->prepareValue($value, $old); // the value may stand elsewhere or hold this node
		}

		if ($this->parent === null) { // standing nowhere, as every node while it is built: no file to report to
			if ($value) {
				$value->parent = $this;
			}

			if ($old?->parent === $this) {
				$old->parent = null;
			}

			return $value;
		}

		$this->exchangeChild($old, $value);
		return $value;
	}


	/**
	 * Releases the current child of a slot and adopts the new one in a node standing under another, and tells
	 * the file, if there is one.
	 */
	protected function exchangeChild(self|Token|null $old, self|Token|null $value): void
	{
		$this->release($old); // before the adoption: the index reads the tree when it is told of a release
		if ($value) {
			$this->adopt($value);
		}

		$this->structureChanged();
	}


	public function getFile(): ?FileNode
	{
		$node = $this;
		while ($node->parent) {
			$node = $node->parent;
		}

		return $node instanceof FileNode ? $node : null;
	}


	/**
	 * The tokens of the whole subtree in source order; empty for a node without tokens, such as an empty list.
	 * @return list<Token>
	 */
	public function getTokens(): array
	{
		$tokens = [];
		$stack = [$this];
		while ($stack) {
			$node = array_pop($stack);
			if ($node instanceof Token) {
				$tokens[] = $node;
				continue;
			}

			$children = $node->getChildren();
			for ($i = count($children) - 1; $i >= 0; $i--) {
				$stack[] = $children[$i];
			}
		}

		return $tokens;
	}


	/** Null only for a node without tokens. */
	public function getFirstToken(): ?Token
	{
		foreach (static::Slots as $slot) {
			$child = $this->$slot;
			if ($token = $child?->getFirstToken()) {
				return $token;
			}
		}

		return null;
	}


	public function getLastToken(): ?Token
	{
		for ($i = count(static::Slots) - 1; $i >= 0; $i--) {
			$child = $this->{static::Slots[$i]};
			if ($token = $child?->getLastToken()) {
				return $token;
			}
		}

		return null;
	}


	/** Current line of the first token; null for a detached subtree or a node without tokens. */
	public function getStartLine(): ?int
	{
		return $this->getFirstToken()?->currentLine;
	}


	/** Current line where the last token ends; null as for `getStartLine()`. */
	public function getEndLine(): ?int
	{
		$token = $this->getLastToken();
		$line = $token?->currentLine;
		return $line === null ? null : $line + TokenIndex::countLineEndings($token->text);
	}


	/**
	 * Whether a line ends inside the text of the node, the trivia on its outer edges left out; unlike the lines,
	 * it needs no file.
	 */
	public function isMultiLine(): bool
	{
		$tokens = $this->getTokens();
		$last = count($tokens) - 1;
		foreach ($tokens as $i => $token) {
			if (preg_match('~[\r\n]~', $i === $last ? rtrim($token->text, "\r\n") : $token->text)) {
				return true;
			}

			foreach ($i === $last ? [] : [...$token->trailingTrivia, ...$tokens[$i + 1]->leadingTrivia] as $trivia) {
				if (preg_match('~[\r\n]~', $trivia->text)) {
					return true;
				}
			}
		}

		return false;
	}


	/**
	 * The innermost node of the class above this one, its parent first.
	 * @template T of object
	 * @param  class-string<T>  $class
	 * @return (T&Node)|null
	 */
	public function findAncestor(string $class): ?self
	{
		for ($node = $this->parent; $node; $node = $node->parent) {
			if ($node instanceof $class) {
				return $node;
			}
		}

		return null;
	}


	/**
	 * The first descendant of the class the predicate accepts, in pre-order; null when there is none.
	 * @template T of object
	 * @param  class-string<T>  $class  a node class or an interface node classes implement
	 * @param  ?callable(T&Node): bool  $predicate
	 * @return (T&Node)|null
	 */
	public function findFirst(string $class, ?callable $predicate = null): ?self
	{
		return $this->collectDescendants($class, $predicate, firstOnly: true)[0] ?? null;
	}


	/**
	 * Descendant nodes of the class the predicate accepts, in pre-order, as a snapshot safe to iterate
	 * while mutating the tree.
	 * @template T of object
	 * @param  class-string<T>  $class  a node class or an interface node classes implement
	 * @param  ?callable(T&Node): bool  $predicate
	 * @return list<T&Node>
	 */
	public function find(string $class, ?callable $predicate = null): array
	{
		return $this->collectDescendants($class, $predicate, firstOnly: false);
	}


	/**
	 * @template T of object
	 * @param  class-string<T>  $class
	 * @param  ?callable(T&Node): bool  $predicate
	 * @return list<T&Node>
	 */
	private function collectDescendants(string $class, ?callable $predicate, bool $firstOnly): array
	{
		self::checkFilter($class);
		$result = [];
		$stack = array_reverse($this->getChildren());
		while ($stack) {
			$node = array_pop($stack);
			if ($node instanceof Token) {
				continue;
			}

			if ($node instanceof $class && ($predicate === null || $predicate($node))) {
				$result[] = $node;
				if ($firstOnly) {
					break;
				}
			}

			$children = $node->getChildren();
			for ($i = count($children) - 1; $i >= 0; $i--) {
				$stack[] = $children[$i];
			}
		}

		/** @var list<T&Node> $result */
		return $result;
	}


	/** The class the descendants are looked up by must be one a node can be. */
	private static function checkFilter(string $class): void
	{
		if (!is_a($class, self::class, allow_string: true) && !interface_exists($class)) {
			throw new \InvalidArgumentException('The class must be a node class or an interface, ' . Helpers::formatCode($class) . ' given.');
		}
	}


	public function __toString(): string
	{
		return Printer::print($this);
	}


	/**
	 * Deep copy without a parent, over the slots; a property computed from the tokens is never read, because
	 * it may throw, as the value of an interpolating heredoc does.
	 */
	public function __clone()
	{
		$this->parent = null;
		foreach (static::Slots as $slot) {
			if ($this->$slot !== null) {
				$this->$slot = clone $this->$slot; // the set hook adopts the copy and leaves the original alone
			}
		}
	}


	/**
	 * Copies of the children of a node without slots, adopted by the copy.
	 * @template C of Node|Token
	 * @param  list<C>  $children
	 * @return list<C>
	 */
	protected function cloneChildren(array $children): array
	{
		foreach ($children as $i => $child) {
			$copy = clone $child;
			$copy->parent = $this;
			$children[$i] = $copy;
		}

		return $children;
	}


	/**
	 * Everything a write decides before it moves anything: the value is freed where the write may take it
	 * from, and refused where it cannot be taken at all, so that what the tree already holds stays as it is.
	 */
	protected function prepareValue(self|Token $value, self|Token|null $leaving): void
	{
		$this->checkValue($value, $leaving);
		$this->liftFrom($value, $leaving);
	}


	/**
	 * Refuses a value the write cannot take, and moves nothing: one holding the node written into, which
	 * would make a cycle, and one standing where the write may not take it from. A write of several values
	 * asks this of each of them before it frees any.
	 */
	protected function checkValue(self|Token $value, self|Token|null $leaving): void
	{
		for ($node = $this; $node !== null; $node = $node->parent) {
			if ($node === $value) {
				throw new \LogicException('A node cannot be placed inside itself or inside what it holds.');
			}
		}

		if ($value->parent !== null && !$this->canLift($value, $leaving)) {
			throw new \LogicException('The node already belongs to a tree, `clone` it first.');
		}
	}


	/**
	 * Frees a value the write may take: one standing inside the child that is leaving the tree, and one
	 * standing in a subtree without a file. What is left of either still points at the value, so it is
	 * waste and not material.
	 */
	protected function liftFrom(self|Token $value, self|Token|null $leaving): void
	{
		if ($value->parent !== null && $this->canLift($value, $leaving)) {
			$value->parent = null;
		}
	}


	/**
	 * Whether the write may take the value from where it stands: out of the child leaving the tree, or out of
	 * a subtree without a file, which has no index that could come apart; never out of this node, because
	 * taking a child from itself would put it in twice.
	 */
	private function canLift(self|Token $value, self|Token|null $leaving): bool
	{
		$root = $value;
		for ($ancestor = $value->parent; $ancestor !== null; $ancestor = $ancestor->parent) {
			if ($ancestor === $leaving) {
				return true;
			}

			$root = $ancestor;
		}

		return !$root instanceof FileNode && $value->parent !== $this;
	}


	/**
	 * Takes over a child that is not part of any tree yet, or one the write may take from where it stands.
	 */
	protected function adopt(self|Token $child): void
	{
		$this->liftFrom($child, null);
		if ($child->parent) {
			throw new \LogicException('The node already belongs to a tree, `clone` it first.');
		}

		$child->parent = $this;
		$this->getFile()?->adopted($child);
	}


	protected function release(self|Token|null $child): void
	{
		if ($child?->parent === $this) { // a clone under construction still holds the children of the original
			$this->getFile()?->released($child);
			$child->parent = null;
		}
	}


	/**
	 * Lets go of the children of a node that is dropped, so that they can stand elsewhere.
	 * @internal the parser takes apart what it built
	 */
	public function dismantle(): void
	{
		foreach ($this->getChildren() as $child) {
			$child->parent = null;
		}
	}


	/**
	 * Tells the file that the children changed: every `adopt()` and `release()` since the last call is in place.
	 */
	protected function structureChanged(): void
	{
		$this->getFile()?->structureChanged();
	}


	protected static function describeChildMismatch(self|Token $child): \InvalidArgumentException
	{
		return new \InvalidArgumentException(self::formatChild($child) . ' is not a child of `' . static::class . '`.');
	}


	private static function formatChild(self|Token $child): string
	{
		return $child instanceof Token ? 'Token ' . Helpers::formatCode($child->text) : '`' . $child::class . '`';
	}
}
