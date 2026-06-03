<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Helpers, Node, Token, Trivia};
use function count;


/**
 * Sequence of nodes, plain or separated by tokens. It has no slots and reads its items itself, and it is read as
 * a collection: iterated, counted and indexed (`$list[0]`), `getItems()` giving the items as an array. It changes
 * only through its own methods.
 * @template T of Node
 * @implements \IteratorAggregate<int, T>
 * @implements \ArrayAccess<int, T>
 */
abstract class NodeList extends Node implements \Countable, \IteratorAggregate, \ArrayAccess
{
	/** @var list<T> */
	protected array $items = [];


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
	abstract public function append(Node $item): void;


	/**
	 * Inserts an item at the index.
	 * @param T $item
	 */
	abstract public function insert(int $index, Node $item): void;


	/** Takes the item out with its trivia and tidies nothing; `Node::remove()` takes the lines with it. */
	abstract public function removeItem(Node $item): void;


	public function indexOf(Node $item): int
	{
		$index = array_search($item, $this->items, strict: true);
		if ($index === false) {
			throw self::describeChildMismatch($item);
		}

		return $index;
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


	/** @param int $offset */
	public function offsetExists(mixed $offset): bool
	{
		return isset($this->items[$offset]);
	}


	/**
	 * @param int $offset
	 * @return T
	 */
	public function offsetGet(mixed $offset): Node
	{
		return $this->items[$offset]
			?? throw new \OutOfRangeException("Index $offset is out of range, the list has " . count($this->items) . ' items.');
	}


	public function offsetSet(mixed $offset, mixed $value): never
	{
		throw new \LogicException('A list is changed by its methods, `append()`, `insert()`, `removeItem()` and `replaceChild()`.');
	}


	public function offsetUnset(mixed $offset): never
	{
		throw new \LogicException('A list is changed by its methods, `append()`, `insert()`, `removeItem()` and `replaceChild()`.');
	}


	/** Null only for an empty list. */
	public function getFirstToken(): ?Token
	{
		foreach ($this->getChildren() as $child) {
			if ($token = $child->getFirstToken()) {
				return $token;
			}
		}

		return null;
	}


	public function getLastToken(): ?Token
	{
		$children = $this->getChildren();
		for ($i = count($children) - 1; $i >= 0; $i--) {
			if ($token = $children[$i]->getLastToken()) {
				return $token;
			}
		}

		return null;
	}


	/**
	 * Gives the node the leading indentation of the model, where the model starts a line and the node
	 * carries no leading trivia of its own.
	 */
	protected static function indentLike(Node $node, Node $model): void
	{
		$target = $node->getFirstToken();
		$source = $model->getFirstToken();
		if (!$target || !$source || $target->leadingTrivia) {
			return;
		}

		$indentation = [];
		foreach ($source->leadingTrivia as $trivia) {
			$indentation = $trivia->is(Trivia::Whitespace) ? [...$indentation, $trivia] : [];
		}

		if ($source->startsLine()) {
			$target->setLeadingTrivia($indentation);
		}
	}


	/**
	 * Inserts the item into the list in place; an append moves nothing, so a list is built item by item
	 * without copying what is in it already, which is what a parser does thousands of times.
	 * @template U
	 * @param  list<U>  $list
	 * @param  U  $item
	 * @param-out list<U>  $list
	 */
	protected static function insertInto(array &$list, int $index, mixed $item): void
	{
		if ($index === count($list)) {
			$list[] = $item;
		} else {
			$list = Helpers::spliceList($list, $index, 0, [$item]);
		}
	}
}
