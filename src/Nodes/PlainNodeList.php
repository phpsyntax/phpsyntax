<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Helpers, Node, Token};
use function count;


/**
 * Sequence of nodes without separators: statements, members, attribute groups.
 * @template T of Node
 * @extends NodeList<T>
 */
final class PlainNodeList extends NodeList
{
	/**
	 * @internal
	 * @param list<T> $items
	 */
	public function __construct(array $items = [])
	{
		$this->items = $items;
		foreach ($items as $item) {
			if ($item->parent === null) { // being built, nothing to check
				$item->parent = $this;
			} else {
				$this->adopt($item);
			}
		}
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
			throw new \OutOfRangeException("Index $index is out of range, the list has " . count($this->items) . ' items.');
		}

		// a list being built, as the parser builds it: no file to report to, and an item standing nowhere
		// cannot be taken from anywhere nor hold the list without a parent, unless it is the list itself
		if ($this->parent === null && $item->parent === null && $item !== $this) {
			$item->parent = $this;
			self::insertInto($this->items, $index, $item);
			return;
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


	/** Inserts the new item right after the item, as `insert()` does. */
	public function insertAfter(Node $item, Node $new): void
	{
		$this->insert($this->indexOf($item) + 1, $new);
	}


	/** Inserts the new item right before the item, as `insert()` does. */
	public function insertBefore(Node $item, Node $new): void
	{
		$this->insert($this->indexOf($item), $new);
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
		$this->items = Helpers::spliceList($this->items, $index, 1);
		$this->structureChanged();
	}


	public function getChildren(): array
	{
		return $this->items;
	}


	public function findSlotOf(Node|Token $child): ?string
	{
		return in_array($child, $this->items, true) ? 'items' : null;
	}


	public function replaceChild(Node|Token $old, Node|Token $new): void
	{
		$index = $old instanceof Node ? $this->indexOf($old) : throw self::describeChildMismatch($old);
		if (!$new instanceof Node) {
			throw new \InvalidArgumentException('A token cannot be an item of `' . static::class . '`.');
		} elseif ($new === $old) {
			return;
		}

		/** @var T $new  the item type is erased at runtime */
		$this->prepareValue($new, $old);
		$this->release($old);
		$this->adopt($new);
		$this->items = Helpers::spliceList($this->items, $index, 1, [$new]);
		$this->structureChanged();
	}


	public function __clone()
	{
		parent::__clone();
		$this->items = $this->cloneChildren($this->items);
	}
}
