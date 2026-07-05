<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Token;
use function count, in_array;


/**
 * Sequence of nodes without separators: statements, members, attribute groups.
 * @template T of Node
 * @implements \IteratorAggregate<int, T>
 */
final class NodeList extends Node implements \Countable, \IteratorAggregate
{
	/**
	 * @internal
	 */
	public function __construct(
		/** @var list<T>  the items; only the list writes them, through its own methods */
		public protected(set) array $items = [],
	) {
		foreach ($items as $item) {
			$this->adopt($item);
		}
	}


	/** @return list<T> */
	public function getItems(): array
	{
		return $this->items;
	}


	public function isEmpty(): bool
	{
		return $this->items === [];
	}


	/** @param T $item */
	public function append(Node $item): void
	{
		$this->prepareValue($item, null);
		$this->adopt($item);
		$this->items[] = $item;
		$this->structureChanged();
	}


	/** @param T $item */
	public function insert(int $index, Node $item): void
	{
		$this->prepareValue($item, null);
		$this->adopt($item);
		self::insertInto($this->items, $index, $item);
		$this->structureChanged();
	}


	public function removeItem(Node $item): void
	{
		$index = $this->indexOf($item);
		$this->release($item);
		$this->items = self::spliceList($this->items, $index, 1);
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
		return $this->items;
	}


	public function findSlotOf(Node|Token $child): ?string
	{
		return in_array($child, $this->items, strict: true) ? 'items' : null;
	}


	public function replaceChild(Node|Token $old, Node|Token $new): void
	{
		$index = $old instanceof Node ? $this->indexOf($old) : throw self::describeChildMismatch($old);
		if (!$new instanceof Node) {
			throw new \InvalidArgumentException('A token cannot be an item of ' . self::class . '.');
		}

		/** @var T $new  the item type is erased at runtime */
		$this->prepareValue($new, $old);
		$this->release($old);
		$this->adopt($new);
		$this->items = self::spliceList($this->items, $index, 1, [$new]);
		$this->structureChanged();
	}


	public function count(): int
	{
		return count($this->items);
	}


	/**
	 * The items, as a snapshot safe to iterate while mutating the list.
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
	}
}
