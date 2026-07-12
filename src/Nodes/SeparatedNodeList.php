<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Token;
use PhpSyntax\Trivia;
use PhpSyntax\TriviaKind;
use function count, in_array, ord;


/**
 * Sequence of nodes with separator tokens between them and an optional trailing separator:
 * parameters, arguments, array items, imports. An item may be an empty node standing for
 * nothing between two separators ([, $b] = $x).
 * @template T of Node
 * @implements \IteratorAggregate<int, T>
 */
final class SeparatedNodeList extends Node implements \Countable, \IteratorAggregate
{
	/**
	 * @internal
	 */
	public function __construct(
		/** @var list<T>  the items; only the list writes them, through its own methods */
		public protected(set) array $items = [],
		/** @var list<Token>  one before each item but the first, plus an optional trailing one */
		public protected(set) array $separators = [],
	) {
		foreach ([...$items, ...$separators] as $child) {
			$this->adopt($child);
		}
	}


	/** @return list<T> */
	public function getItems(): array
	{
		return $this->items;
	}


	/** @return list<Token> */
	public function getSeparators(): array
	{
		return $this->separators;
	}


	public function isEmpty(): bool
	{
		return $this->items === [];
	}


	public function hasTrailingSeparator(): bool
	{
		return count($this->separators) === count($this->items) && $this->items !== [];
	}


	/**
	 * Appends an item; the separator before it is derived from the existing ones unless given.
	 * @param T $item
	 */
	public function append(Node $item, ?Token $separator = null): void
	{
		$this->insert(count($this->items), $item, $separator);
	}


	/**
	 * Inserts an item at the index. A missing separator is modeled on the existing ones, or on ", " in
	 * a one-line list; in a multi-line list the item also inherits the indentation of its neighbor.
	 * @param T $item
	 */
	public function insert(int $index, Node $item, ?Token $separator = null): void
	{
		if ($index < 0 || $index > count($this->items)) {
			throw new \OutOfRangeException("Index $index is out of range.");
		} elseif ($separator && $this->items === []) {
			throw new \LogicException('The first item has no separator before it.');
		}

		// both values are checked before either moves, so a refused insertion leaves both of their trees as they were
		$this->checkValue($item, null);
		if ($separator) {
			$this->checkValue($separator, null);
			for ($node = $separator->parent; $node !== null; $node = $node->parent) {
				if ($node === $item) {
					throw new \LogicException('The separator cannot be a part of the item it separates.');
				}
			}
		}

		$this->liftFrom($item, null);
		if ($separator) {
			$this->liftFrom($separator, null);
		} elseif ($this->items !== []) {
			$neighbor = $this->items[$index > 0 ? $index - 1 : 0];
			$separator = $this->deriveSeparator($index, $neighbor);
			self::indentLike($item, $neighbor);
			if ($index > 0) {
				$this->endLineLike($item, $neighbor);
			}
		}

		$this->adopt($item);
		self::insertInto($this->items, $index, $item);
		if ($separator) {
			$this->adopt($separator);
			self::insertInto($this->separators, max($index - 1, 0), $separator);
		}

		$this->structureChanged();
	}


	public function setTrailingSeparator(?Token $separator): void
	{
		if ($separator) {
			$this->prepareValue($separator, null);
		}

		if ($this->hasTrailingSeparator()) {
			$this->release(array_pop($this->separators));
		}

		if ($separator) {
			$this->adopt($separator);
			$this->separators[] = $separator;
		}

		$this->structureChanged();
	}


	/**
	 * Removes the item together with the separator that goes with it (see findSeparatorOf()).
	 */
	public function removeItem(Node $item): void
	{
		$index = $this->indexOf($item);
		$separator = $this->findSeparatorOf($item);
		$this->release($item);
		$this->items = self::spliceList($this->items, $index, 1);
		if ($this->items === []) {
			array_walk($this->separators, $this->release(...));
			$this->separators = [];
		} elseif ($separator !== null) {
			$this->release($separator);
			$this->separators = self::spliceList($this->separators, (int) array_search($separator, $this->separators, strict: true), 1);
		}

		$this->structureChanged();
	}


	/**
	 * The separator that goes when the item goes: the one after it, and for the last item the one before it.
	 */
	public function findSeparatorOf(Node $item): ?Token
	{
		$index = $this->indexOf($item);
		return $this->separators[$index === count($this->items) - 1 ? $index - 1 : $index] ?? null;
	}


	public function indexOf(Node $item): int
	{
		$index = array_search($item, $this->items, strict: true);
		if ($index === false) {
			throw self::describeChildMismatch($item);
		}

		return $index;
	}


	public function getChildren(): array
	{
		$children = [];
		foreach ($this->items as $index => $item) {
			if ($index > 0) {
				$children[] = $this->separators[$index - 1];
			}

			$children[] = $item;
		}

		if ($this->hasTrailingSeparator()) {
			$children[] = $this->separators[count($this->items) - 1];
		}

		return $children;
	}


	public function findSlotOf(Node|Token $child): ?string
	{
		return match (true) {
			in_array($child, $this->items, strict: true) => 'items',
			in_array($child, $this->separators, strict: true) => 'separators',
			default => null,
		};
	}


	public function replaceChild(Node|Token $old, Node|Token $new): void
	{
		if ($old instanceof Node && $new instanceof Node) {
			$index = $this->indexOf($old);
			/** @var T $new  the item type is erased at runtime */
			$this->prepareValue($new, $old);
			$this->release($old);
			$this->adopt($new);
			$this->items = self::spliceList($this->items, $index, 1, [$new]);

		} elseif ($old instanceof Token && $new instanceof Token) {
			$index = array_search($old, $this->separators, strict: true);
			if ($index === false) {
				throw self::describeChildMismatch($old);
			}

			$this->prepareValue($new, $old);
			$this->release($old);
			$this->adopt($new);
			$this->separators = self::spliceList($this->separators, $index, 1, [$new]);

		} else {
			throw new \InvalidArgumentException('An item can be replaced only by a node and a separator only by a token.');
		}

		$this->structureChanged();
	}


	public function count(): int
	{
		return count($this->items);
	}


	/**
	 * The items without the separators between them, as a snapshot safe to iterate while mutating the list.
	 * @return \ArrayIterator<int, T>
	 */
	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->items);
	}


	public function __clone()
	{
		parent::__clone();
		$this->items = $this->cloneChildren($this->items);
		$this->separators = $this->cloneChildren($this->separators);
	}


	/**
	 * A separator modeled on the ones already there, the nearest one first; a comment of a separator is
	 * content and not formatting, so one carrying it is no model and the next one stands in for it.
	 */
	private function deriveSeparator(int $index, Node $neighbor): Token
	{
		$nearest = $this->separators[min($index, count($this->separators)) - 1] ?? $this->separators[0] ?? null;
		foreach ([$nearest, ...$this->separators] as $model) {
			if ($model !== null && !$model->hasComment()) {
				return clone $model;
			}
		}

		$separator = new Token(ord(','), ',');
		$eol = self::findLineEnding($neighbor);
		$separator->setTrailingTrivia([$eol ?? new Trivia(TriviaKind::Whitespace, ' ')]);
		return $separator;
	}


	/**
	 * When the neighbor ends its line, the new item after it takes over that role.
	 */
	private function endLineLike(Node $item, Node $neighbor): void
	{
		$source = $neighbor->getLastToken();
		$target = $item->getLastToken();
		$trailing = $source === null ? [] : $source->trailingTrivia; // a copy: a hooked property takes no indirect change
		if (
			$source
			&& $target
			&& $trailing
			&& end($trailing)->isEndOfLine()
			&& !$target->trailingTrivia
		) {
			$target->setTrailingTrivia($trailing);
			$source->setTrailingTrivia([]);
		}
	}


	/** The line ending before the item when it starts a line. */
	private static function findLineEnding(Node $item): ?Trivia
	{
		$token = $item->getFirstToken();
		if (!$token) {
			return null;
		}

		foreach ([array_reverse($token->leadingTrivia), array_reverse($token->getPrevious()->trailingTrivia ?? [])] as $trivias) {
			foreach ($trivias as $trivia) {
				if ($trivia->kind === TriviaKind::EndOfLine) {
					return $trivia;
				} elseif ($trivia->kind !== TriviaKind::Whitespace) {
					return null;
				}
			}
		}

		return null;
	}
}
