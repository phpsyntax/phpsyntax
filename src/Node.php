<?php declare(strict_types=1);

namespace PhpSyntax;

use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\SeparatedNodeList;
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
	 * The node as it is written, without the trivia on its outer edges: what stands between its tokens
	 * belongs to it, what stands before the first and after the last one belongs to the file around it.
	 * Printing the node writes those edges too, which is what the round trip needs and a report does not.
	 */
	public string $text {
		get {
			$text = '';
			$previous = null;
			foreach ($this->getTokens() as $token) {
				if ($previous !== null) { // what stands between two tokens, so the edges never come up
					$text .= self::textOf($previous->trailingTrivia) . self::textOf($token->leadingTrivia);
				}

				$text .= $token->text;
				$previous = $token;
			}

			return $text;
		}
	}

	/**
	 * The trivia before the node, which are the leading trivia of its first token; setEdgeTrivia() writes them.
	 * @var list<Trivia>
	 */
	public array $leadingTrivia {
		get => $this->getFirstToken()->leadingTrivia ?? [];
	}

	/**
	 * The trivia after the node, which are the trailing trivia of its last token; setEdgeTrivia() writes them.
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


	/**
	 * The text the trivia stand for.
	 * @param  list<Trivia>  $trivia
	 */
	private static function textOf(array $trivia): string
	{
		$text = '';
		foreach ($trivia as $item) {
			$text .= $item->text;
		}

		return $text;
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


	/** Current line of the first token; null for a detached subtree or a node without tokens. */
	public function getStartLine(): ?int
	{
		return $this->getFirstToken()?->getLine();
	}


	/** Current line where the last token ends. */
	public function getEndLine(): ?int
	{
		$token = $this->getLastToken();
		$line = $token?->getLine();
		return $line === null ? null : $line + preg_match_all('~\r\n|\r|\n~', $token->text);
	}


	/**
	 * Doc comment before the node: the last one in the leading trivia of the first token, or in the trailing
	 * trivia of the previous token (public $a; /** @var int * / public $b;).
	 */
	public function getDocComment(): ?Trivia
	{
		$token = $this->getFirstToken();
		if (!$token) {
			return null;
		}

		foreach ([$token->leadingTrivia, $token->getPrevious()->trailingTrivia ?? []] as $trivias) {
			for ($i = count($trivias) - 1; $i >= 0; $i--) {
				if ($trivias[$i]->kind === TriviaKind::DocComment) {
					return $trivias[$i];
				}
			}
		}

		return null;
	}


	/**
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
		self::checkFilter($class);
		$accepts = static fn(self $node): bool => $node instanceof $class && ($predicate === null || $predicate($node));
		$found = null;
		new Traverser()->traverse($this, function (self|Token $node) use ($accepts, &$found): ?int {
			if ($node !== $this && $node instanceof self && $accepts($node)) {
				$found = $node;
				return Traverser::StopTraversal;
			}

			return null;
		});
		/** @var (T&Node)|null $found */
		return $found;
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
			throw new \InvalidArgumentException("The class must be a node class or an interface, '$class' given.");
		}
	}


	/**
	 * Whether the tokens of both nodes carry the same texts, whatever the whitespace between them.
	 */
	public function matches(self $other): bool
	{
		return self::collectTexts($this) === self::collectTexts($other);
	}


	/** @return list<string> */
	private static function collectTexts(self|Token $item): array
	{
		if ($item instanceof Token) {
			return [$item->text];
		}

		$texts = [];
		foreach ($item->getChildren() as $child) {
			foreach (self::collectTexts($child) as $text) {
				$texts[] = $text;
			}
		}

		return $texts;
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


	/**
	 * Replaces this node in its parent; the trivia around the old node stay in place around the new one.
	 */
	public function replaceWith(self $node): void
	{
		$parent = $this->parent ?? throw new \LogicException('Cannot replace a node without a parent.');
		$parent->replaceChild($this, $node); // the parent takes the node before the trivia move, so a refused one leaves them where they stand
		if ($first = $this->getFirstToken()) {
			$leading = $first->leadingTrivia;
			$first->setLeadingTrivia([]);
			if ($target = $node->getFirstToken()) {
				$target->setLeadingTrivia([...$leading, ...$target->leadingTrivia]);
			}
		}

		if ($last = $this->getLastToken()) {
			$trailing = $last->trailingTrivia;
			$last->setTrailingTrivia([]);
			if ($target = $node->getLastToken()) {
				$target->setTrailingTrivia([...$target->trailingTrivia, ...$trailing]);
			}
		}
	}


	/**
	 * Removes this node from its list. A node alone on its lines takes the lines with it (indentation and
	 * line ending), otherwise the whitespace around stays; comments inside, each with the line ending that
	 * follows it, go where the policy says.
	 */
	public function remove(CommentPolicy $comments = CommentPolicy::MoveToNextToken): void
	{
		$parent = $this->parent;
		if (!$parent instanceof NodeList && !$parent instanceof SeparatedNodeList) {
			throw new \LogicException('Only an item of a list can be removed; use the setter of the slot instead.');
		}

		$tokens = $this->getTokens();
		$separatorAfter = false;
		if ($parent instanceof SeparatedNodeList && ($separator = $parent->findSeparatorOf($this)) !== null) {
			$separatorAfter = $separator !== ($tokens[0] ?? null)?->getPrevious(); // the last item has it before
			$tokens = $separatorAfter ? [...$tokens, $separator] : [$separator, ...$tokens];
		}

		$first = $tokens[0] ?? null;
		$last = $tokens[count($tokens) - 1] ?? null;
		$previous = $first?->getPrevious();
		$next = $last?->getNext();
		[$leading, $moved] = self::splitComments($first->leadingTrivia ?? []);
		foreach ($tokens as $token) {
			foreach ([$token === $first ? [] : $token->leadingTrivia, $token === $last ? [] : $token->trailingTrivia] as $trivias) {
				$moved = [...$moved, ...self::splitComments($trivias)[1]];
			}
		}

		[$trailing, $trailingComments] = self::splitComments($last->trailingTrivia ?? []);
		$moved = [...$moved, ...$trailingComments];
		if ($separatorAfter) { // the gap the separator opened goes with it, the line ending of the item stays
			$trailing = array_values(array_filter($trailing, fn(Trivia $trivia) => $trivia->isEndOfLine()));
		}

		if (self::standsAlone($first->leadingTrivia ?? [], $last->trailingTrivia ?? [], $previous, $next)) {
			if ($leading && end($leading)->kind === TriviaKind::Whitespace) {
				array_pop($leading);
			}

			$trailing = [];
		}

		$before = $after = [];
		if ($comments === CommentPolicy::MoveToPreviousToken && $previous) {
			$before = $moved;
		} elseif ($comments !== CommentPolicy::Drop) {
			$after = $moved;
		}

		if ($previous) {
			$previous->setTrailingTrivia([...$previous->trailingTrivia, ...$before, ...$trailing]);
			$trailing = [];
			$ends = $previous->trailingTrivia; // a copy: a hooked property takes no indirect change
			if ($ends && end($ends)->isEndOfLine()) {
				$previous->removeTrailingWhitespace(); // the line ends here now, so nothing dangles before it
			}
		}

		if ($next) {
			$next->setLeadingTrivia([...$leading, ...$after, ...$trailing, ...$next->leadingTrivia]);
		} elseif ($previous) {
			$previous->setTrailingTrivia([...$previous->trailingTrivia, ...$leading, ...$after]);
		}

		$parent->removeItem($this);
	}


	public function __toString(): string
	{
		return Printer::print($this);
	}


	/**
	 * Deep copy without a parent; the copy takes the children the slots name, never what a property
	 * computes from the tokens, which is work nobody asked for and which a heredoc refuses to do.
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
	 * Copies of the children of a list, adopted by the copy; a list has no slots to copy by and holds
	 * its children in an array of its own.
	 * @template C of self|Token
	 * @param  list<C>  $children
	 * @return list<C>
	 */
	protected function cloneChildren(array $children): array
	{
		foreach ($children as $i => $child) {
			$copy = clone $child;
			$copy->attachTo($this);
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
		$this->getFile()?->adopted($child);
	}


	protected function release(self|Token|null $child): void
	{
		if ($child?->parent === $this) { // a clone under construction still holds the children of the original
			$this->getFile()?->released($child);
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
	 * Gives the node the leading indentation of the model, where the model starts a line and the node
	 * carries no leading trivia of its own; an item added to a list stands where its neighbor stands.
	 */
	protected static function indentLike(self $node, self $model): void
	{
		$target = $node->getFirstToken();
		$source = $model->getFirstToken();
		if (!$target || !$source || $target->leadingTrivia) {
			return;
		}

		$indentation = [];
		foreach ($source->leadingTrivia as $trivia) {
			$indentation = $trivia->kind === TriviaKind::Whitespace ? [...$indentation, $trivia] : [];
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
	 * Separates comments, each with the line ending directly after it, from the rest of the trivia.
	 * @param  list<Trivia>  $trivias
	 * @return array{list<Trivia>, list<Trivia>}  [rest, comments]
	 */
	private static function splitComments(array $trivias): array
	{
		$rest = $comments = [];
		foreach ($trivias as $i => $trivia) {
			if ($trivia->isComment()) {
				$comments[] = $trivia;
			} elseif ($trivia->kind === TriviaKind::EndOfLine && $i > 0 && $trivias[$i - 1]->isComment()) {
				$comments[] = $trivia;
			} else {
				$rest[] = $trivia;
			}
		}

		return [$rest, $comments];
	}


	/**
	 * Whether nothing but whitespace and comments shares the lines of the node.
	 * @param list<Trivia> $leading
	 * @param list<Trivia> $trailing
	 */
	private static function standsAlone(array $leading, array $trailing, ?Token $previous, ?Token $next): bool
	{
		$startsLine = false;
		for ($i = count($leading) - 1; $i >= 0; $i--) {
			if ($leading[$i]->isEndOfLine()) {
				$startsLine = true;
				break;
			} elseif ($leading[$i]->kind !== TriviaKind::Whitespace && !$leading[$i]->isComment()) {
				return false;
			}
		}

		if (!$startsLine) {
			$before = $previous->trailingTrivia ?? [];
			$startsLine = !$previous || ($before && end($before)->isEndOfLine());
		}

		$endsLine = false;
		foreach ($trailing as $trivia) {
			if ($trivia->isEndOfLine()) {
				$endsLine = true;
				break;
			} elseif ($trivia->kind !== TriviaKind::Whitespace && !$trivia->isComment()) {
				return false;
			}
		}

		if (!$endsLine) {
			$after = $next->leadingTrivia ?? [];
			$endsLine = !$next || $next->kind === TokenKind::EndOfFile || ($after && $after[0]->isEndOfLine());
		}

		return $startsLine && $endsLine;
	}
}
