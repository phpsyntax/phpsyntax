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


	/**
	 * Appends an item, which takes the place in the lines of the list its neighbor has.
	 * @param T $item
	 */
	public function append(Node $item): void
	{
		$this->insert(count($this->items), $item);
	}


	/**
	 * Inserts an item at the index. In a list standing in a file, an item that carries no trivia of its own
	 * takes the indentation of its neighbor and ends its line the same way.
	 * @param T $item
	 */
	public function insert(int $index, Node $item): void
	{
		if ($index < 0 || $index > count($this->items)) {
			throw new \OutOfRangeException("Index $index is out of range.");
		}

		$this->prepareValue($item, null); // nothing moves before this, so a refused item leaves the list as it was
		if ($this->items !== [] && $this->getFile() !== null && !$item->leadingTrivia && !$item->trailingTrivia) {
			$neighbor = $this->items[$index > 0 ? $index - 1 : 0];
			self::indentLike($item, $neighbor);
			self::endLike($item, $neighbor);
		}

		$this->adopt($item);
		self::insertInto($this->items, $index, $item);
		$this->structureChanged();
	}


	/**
	 * Ends the item the way its neighbor ends, with a line ending or with a space; a comment of the neighbor
	 * stays with it, and an item ending its line inside its own text needs nothing.
	 */
	private static function endLike(Node $item, Node $neighbor): void
	{
		$target = $item->getLastToken();
		$trailing = $neighbor->trailingTrivia;
		$last = $trailing[count($trailing) - 1] ?? null;
		if ($target !== null && $last?->isWhitespace() && !preg_match('~[\r\n]$~', $target->text)) {
			$target->setTrailingTrivia([$last]);
		}
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
