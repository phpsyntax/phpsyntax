<?php declare(strict_types=1);

namespace PhpSyntax;

use PhpSyntax\Nodes\FileNode;
use function array_slice, count, in_array;


/**
 * Node of the concrete syntax tree; every token of the source is reachable through the children.
 */
abstract class Node implements \Stringable
{
	public const Slots = [];

	/** The node this one belongs to; only the tree writes it, through attachTo(). */
	public private(set) ?Node $parent = null;


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
		$this->setSlot($this->findSlotOf($old) ?? throw self::describeChildMismatch($old), $new);
	}


	/** The slot the direct child stands in; null when it is not a child. */
	public function findSlotOf(self|Token $child): ?string
	{
		foreach (static::Slots as $slot) {
			if ($this->$slot === $child) {
				return $slot;
			}
		}

		return null;
	}


	/**
	 * Writes a slot: the value takes the place of the current one, which leaves the tree; the typed property
	 * refuses a value that does not fit the slot. The generated setters are the typed face of this method.
	 */
	public function setSlot(string $slot, self|Token|null $value): void
	{
		if (!in_array($slot, static::Slots, strict: true)) {
			throw new \InvalidArgumentException("There is no slot '$slot' in " . static::class . '.');
		}

		try {
			$this->$slot = $value;
		} catch (\TypeError) {
			throw new \InvalidArgumentException(
				($value instanceof Token ? "Token '$value->text'" : ($value === null ? 'Nothing' : $value::class)) . " cannot be placed in the slot '$slot' of " . static::class . '.',
			);
		}
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
		if ($value !== null) {
			$this->prepareValue($value, $old);
		}

		if ($this->getFile() === null) { // detached, as every node is while it is built: no file to report to
			$value?->attachTo($this);
			if ($old?->parent === $this) {
				$old->attachTo(null);
			}

			return $value;
		}

		$this->release($old); // before the adoption: the index reads the tree when it is told of a release
		if ($value) {
			$this->adopt($value);
		}

		$this->structureChanged();
		return $value;
	}


	public function getFile(): ?FileNode
	{
		$node = $this;
		while ($node->parent) {
			$node = $node->parent;
		}

		return $node instanceof FileNode ? $node : null;
	}


	public function __toString(): string
	{
		return Printer::print($this);
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
			throw new \LogicException('The node already belongs to a tree, clone it first.');
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
			$value->attachTo(null);
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
			throw new \LogicException('The node already belongs to a tree, clone it first.');
		}

		$child->attachTo($this);
	}


	protected function release(self|Token|null $child): void
	{
		if ($child) {
			$child->attachTo(null);
		}
	}


	/**
	 * Puts the node under another one, or takes it out of the tree with null.
	 * @internal called by Node::adopt(), Node::release() and the parser
	 */
	public function attachTo(?self $parent): void
	{
		$this->parent = $parent;
	}


	/**
	 * Tells the file that the children changed: every adopt() and release() since the last call is in place.
	 */
	protected function structureChanged(): void
	{
		$this->getFile()?->structureChanged();
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
			$list = self::spliceList($list, $index, 0, [$item]);
		}
	}


	/**
	 * Returns the list with $remove items at $index replaced by $insert.
	 * @template U
	 * @param  list<U>  $list
	 * @param  list<U>  $insert
	 * @return list<U>
	 */
	protected static function spliceList(array $list, int $index, int $remove, array $insert = []): array
	{
		return [...array_slice($list, 0, $index), ...$insert, ...array_slice($list, $index + $remove)];
	}


	protected static function describeChildMismatch(self|Token $child): \InvalidArgumentException
	{
		return new \InvalidArgumentException(
			($child instanceof Token ? "Token '$child->text'" : $child::class) . ' is not a child of ' . static::class . '.',
		);
	}


	/**
	 * Writes the trivia on the outer edges of the node: before its first token and after its last one.
	 * A null leaves that edge alone, [] clears it, and a node without tokens takes neither.
	 * @param  ?list<Trivia>  $leading
	 * @param  ?list<Trivia>  $trailing
	 */
	public function setEdgeTrivia(?array $leading = null, ?array $trailing = null): void
	{
		if ($leading !== null && ($first = $this->getFirstToken())) {
			$first->setLeadingTrivia($leading);
		}

		if ($trailing !== null && ($last = $this->getLastToken())) {
			$last->setTrailingTrivia($trailing);
		}
	}


	/** Null only for a node without tokens, such as an empty list. */
	public function getFirstToken(): ?Token
	{
		if (static::Slots === []) { // a list, which reads its items itself
			foreach ($this->getChildren() as $child) {
				if ($token = $child instanceof Token ? $child : $child->getFirstToken()) {
					return $token;
				}
			}

			return null;
		}

		foreach (static::Slots as $slot) {
			$child = $this->$slot;
			if ($token = $child instanceof Token ? $child : $child?->getFirstToken()) {
				return $token;
			}
		}

		return null;
	}


	public function getLastToken(): ?Token
	{
		if (static::Slots === []) {
			$children = $this->getChildren();
			for ($i = count($children) - 1; $i >= 0; $i--) {
				if ($token = $children[$i] instanceof Token ? $children[$i] : $children[$i]->getLastToken()) {
					return $token;
				}
			}

			return null;
		}

		for ($i = count(static::Slots) - 1; $i >= 0; $i--) {
			$child = $this->{static::Slots[$i]};
			if ($token = $child instanceof Token ? $child : $child?->getLastToken()) {
				return $token;
			}
		}

		return null;
	}
}
