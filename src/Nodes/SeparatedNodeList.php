<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Token;
use function count, in_array;


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
	 * @param T $item
	 * @param ?Token $separator  the one before the item; required for any item but the first
	 */
	public function append(Node $item, ?Token $separator = null): void
	{
		if ($this->items === [] xor $separator === null) {
			throw new \LogicException('A separator is required before every item but the first.');
		}

		$this->prepareValue($item, null);
		if ($separator) {
			$this->prepareValue($separator, null);
			$this->adopt($separator);
			$this->separators[] = $separator;
		}

		$this->adopt($item);
		$this->items[] = $item;
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
	 * Removes the item together with the separator after it, or the one before it for the last item.
	 */
	public function removeItem(Node $item): void
	{
		$index = $this->indexOf($item);
		$this->release($item);
		$this->items = self::spliceList($this->items, $index, 1);
		$separator = $this->separators[$index] ?? null;
		$separatorIndex = $separator ? $index : $index - 1;
		if (isset($this->separators[$separatorIndex])) {
			$this->release($this->separators[$separatorIndex]);
			$this->separators = self::spliceList($this->separators, $separatorIndex, 1);
		}

		$this->structureChanged();
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
}
