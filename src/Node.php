<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;

use PhpSyntax\Nodes\{FileNode, NodeList, SeparatedNodeList};
use function count;


/**
 * Node of the concrete syntax tree; every token of the source is reachable through the children.
 */
abstract class Node implements \Stringable
{
	public const Slots = [];

	/** The node this one belongs to; only the tree writes it, through `attachTo()`. */
	public private(set) ?Node $parent = null;

	/**
	 * The node as it is written, without the trivia on its outer edges, which printing it writes too.
	 */
	public string $text {
		get => Printer::printText($this);
	}

	/**
	 * The trivia before the node, which are the leading trivia of its first token; `setEdgeTrivia()` writes them.
	 * @var list<Trivia>
	 */
	public array $leadingTrivia {
		get => $this->getFirstToken()->leadingTrivia ?? [];
	}

	/**
	 * The trivia after the node, which are the trailing trivia of its last token; `setEdgeTrivia()` writes them.
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
	 * Writes a slot given by its name, the way an assignment to the property does: the value takes the place
	 * of the current one, which leaves the tree; the typed property refuses a value that does not fit the slot.
	 */
	public function setSlot(string $slot, self|Token|null $value): static
	{
		if (!in_array($slot, static::Slots, true)) {
			throw new \InvalidArgumentException("There is no slot '$slot' in " . static::class . '.');
		}

		try {
			$this->$slot = $value;
		} catch (\TypeError) {
			throw new \InvalidArgumentException($value === null
				? "The slot '$slot' of " . static::class . ' cannot be empty.'
				: ($value instanceof Token ? "Token '$value->text'" : $value::class) . " cannot be placed in the slot '$slot' of " . static::class . '.');
		}

		return $this;
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


	/**
	 * The texts of the tokens of the subtree in source order, the trivia left out: what `matches()` compares two
	 * nodes by, and what a tool keys a map by where the layout of the code must not count. The size of the code
	 * is measured on `$text`, which keeps the layout.
	 * @return list<string>
	 */
	public function getTokenTexts(): array
	{
		return array_map(fn(Token $token) => $token->text, $this->getTokens());
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


	/** Current line where the last token ends; null as for `getStartLine()`. */
	public function getEndLine(): ?int
	{
		$token = $this->getLastToken();
		$line = $token?->getLine();
		return $line === null ? null : $line + preg_match_all('~\r\n|\r|\n~', $token->text);
	}


	/**
	 * Doc comment before the node: the last one in the leading trivia of the first token, or in the trailing
	 * trivia of the previous token, where a doc comment stands between two declarations on one line.
	 */
	public function getDocComment(): ?Trivia
	{
		$token = $this->getFirstToken();
		if (!$token) {
			return null;
		}

		$previous = $token->getPrevious();
		foreach ([$token->leadingTrivia, $previous->trailingTrivia ?? []] as $trivias) {
			for ($i = count($trivias) - 1; $i >= 0; $i--) {
				if ($trivias[$i]->kind === TriviaKind::DocComment) {
					return $trivias[$i];
				}
			}
		}

		return null;
	}


	/** Replaces one trivia of the node, wherever among its tokens it stands, with another in place. */
	public function replaceTrivia(Trivia $old, Trivia $new): void
	{
		$this->findTriviaOwner($old)->replaceTrivia($old, $new);
	}


	/**
	 * Removes one trivia of the node, wherever among its tokens it stands, tidying the whitespace around it
	 * the way `Token::removeTrivia()` does.
	 */
	public function removeTrivia(Trivia $trivia): void
	{
		$this->findTriviaOwner($trivia)->removeTrivia($trivia);
	}


	/** Replaces the doc comment of the node (see `getDocComment()`) with the trivia given. */
	public function replaceDocComment(Trivia $docComment): void
	{
		$this->replaceTrivia($this->getDocComment() ?? throw new \LogicException('The node has no doc comment.'), $docComment);
	}


	/** Removes the doc comment of the node (see `getDocComment()`) together with the line it stands on. */
	public function removeDocComment(): void
	{
		$this->removeTrivia($this->getDocComment() ?? throw new \LogicException('The node has no doc comment.'));
	}


	/**
	 * The token carrying the trivia, found by identity: one of the node, or the one before it, where
	 * a doc comment of the node may stand.
	 */
	private function findTriviaOwner(Trivia $trivia): Token
	{
		$tokens = $this->getTokens();
		$previous = ($tokens[0] ?? null)?->getPrevious();
		if ($previous !== null) {
			$tokens[] = $previous;
		}

		foreach ($tokens as $token) {
			if (
				in_array($trivia, $token->leadingTrivia, true)
				|| in_array($trivia, $token->trailingTrivia, true)
			) {
				return $token;
			}
		}

		throw new \LogicException('The trivia does not belong to the node.');
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
		$found = null;
		new Traverser()->traverse($this, function (self|Token $node) use ($class, $predicate, &$found): ?int {
			if (
				$node !== $this
				&& $node instanceof self
				&& $node instanceof $class
				&& ($predicate === null || $predicate($node))
			) {
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
		return $this->getTokenTexts() === $other->getTokenTexts();
	}


	/**
	 * Whether a comment sits anywhere between the first and the last token of the node; the trivia
	 * on its outer edges do not count.
	 */
	public function hasComment(): bool
	{
		foreach ($this->walkInnerTrivia() as $trivia) {
			if ($trivia->isComment()) {
				return true;
			}
		}

		return false;
	}


	/**
	 * The comments inside the node, in source order; those on its outer edges are not among them,
	 * the same way `hasComment()` does not count them.
	 * @return list<Trivia>
	 */
	public function getComments(): array
	{
		$comments = [];
		foreach ($this->walkInnerTrivia() as $trivia) {
			if ($trivia->isComment()) {
				$comments[] = $trivia;
			}
		}

		return $comments;
	}


	/**
	 * The trivia between the first and the last token of the node, in source order; the edges are left out.
	 * @return \Generator<Trivia>
	 */
	private function walkInnerTrivia(): \Generator
	{
		$previous = null;
		$stack = [$this];
		while ($stack) {
			$node = array_pop($stack);
			if ($node instanceof Token) {
				if ($previous !== null) { // what stands between two tokens, so the edges never come up
					yield from $previous->trailingTrivia;
					yield from $node->leadingTrivia;
				}

				$previous = $node;
				continue;
			}

			$children = $node->getChildren();
			for ($i = count($children) - 1; $i >= 0; $i--) {
				$stack[] = $children[$i];
			}
		}
	}


	/**
	 * Writes the trivia on the outer edges of the node: before its first token and after its last one.
	 * A `null` leaves that edge alone, `[]` clears it, and a node without tokens takes neither.
	 * @param  ?list<Trivia>  $leading
	 * @param  ?list<Trivia>  $trailing
	 */
	public function setEdgeTrivia(?array $leading = null, ?array $trailing = null): static
	{
		if ($leading !== null && ($first = $this->getFirstToken())) {
			$first->setLeadingTrivia($leading);
		}

		if ($trailing !== null && ($last = $this->getLastToken())) {
			$last->setTrailingTrivia($trailing);
		}

		return $this;
	}


	/** A deep copy without a parent and without the trivia on its outer edges, which belong to the place it was copied from. */
	public function withoutEdgeTrivia(): static
	{
		$copy = clone $this;
		$copy->setEdgeTrivia([], []);
		return $copy;
	}


	/**
	 * Replaces this node in its parent; the trivia around the old node stay in place around the new one, and
	 * where the new one then stands right against a token it would be read together with, `.` against `1` or
	 * `return` against `FOO`, a space keeps the two apart.
	 */
	public function replaceWith(self $node): void
	{
		$parent = $this->parent ?? throw new \LogicException('A node without a parent cannot be replaced.');
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

		if (($first = $node->getFirstToken()) && ($last = $node->getLastToken())) {
			self::keepApart(self::findNeighbor($first, -1), $first);
			self::keepApart($last, self::findNeighbor($last, 1));
		}
	}


	/** Puts a space between two tokens standing right against each other that the lexer would not read as the two. */
	private static function keepApart(?Token $left, ?Token $right): void
	{
		static $lexer = new Lexer\Lexer;
		if (
			$left === null
			|| $right === null
			|| $right->text === ''
			|| $left->trailingTrivia
			|| $right->leadingTrivia
			|| $left->is(TokenKind::EncapsedAndWhitespace, TokenKind::InlineHtml)
			|| $right->is(TokenKind::EncapsedAndWhitespace, TokenKind::InlineHtml)
		) {
			return;
		}

		if (!$lexer->canAdjoin($left->text, $right->text)) {
			$left->setTrailingTrivia([new Trivia(TriviaKind::Whitespace, ' ')]);
		}
	}


	/** The token before or after the given one, in a subtree without a file as well, where no index answers. */
	private static function findNeighbor(Token $token, int $step): ?Token
	{
		if ($token->getFile() !== null) {
			return $step < 0 ? $token->getPrevious() : $token->getNext();
		}

		for ($root = $token->parent; $root?->parent !== null; $root = $root->parent);
		$tokens = $root?->getTokens() ?? [];
		$index = array_search($token, $tokens, true);
		return $index === false ? null : $tokens[$index + $step] ?? null;
	}


	/**
	 * Removes this node from its list, together with the separator that goes with it. A node alone on its
	 * lines takes the lines with it (indentation and line ending), otherwise the whitespace around stays; its
	 * comments, those on its edges included, go where the policy says, each with its indentation and the line
	 * ending after it.
	 */
	public function remove(CommentPolicy $comments = CommentPolicy::MoveToNextToken): void
	{
		$parent = $this->parent;
		if (!$parent instanceof NodeList && !$parent instanceof SeparatedNodeList) {
			throw new \LogicException('Only an item of a list can be removed; a slot is emptied by its setter.');
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
		[$leading, $moved] = self::splitComments($first->leadingTrivia ?? [], $first?->startsLine() ?? false);
		foreach ($tokens as $token) {
			if ($token !== $first) {
				$moved = [...$moved, ...self::splitComments($token->leadingTrivia, $token->startsLine())[1]];
			}

			if ($token !== $last) {
				$moved = [...$moved, ...self::splitComments($token->trailingTrivia, atLineStart: false)[1]];
			}
		}

		[$trailing, $trailingComments] = self::splitComments($last->trailingTrivia ?? [], atLineStart: false);
		$moved = [...$moved, ...$trailingComments];
		if ($separatorAfter) { // the gap the separator opened goes with it, the line ending of the item stays
			$trailing = array_values(array_filter($trailing, fn(Trivia $trivia) => $trivia->isEndOfLine()));
		}

		if (self::standsAlone($first->leadingTrivia ?? [], $last->trailingTrivia ?? [], $previous, $next)) {
			if ($leading && end($leading)->kind === TriviaKind::Whitespace) {
				// a comment that ended the line of the node now stands on a line of its own, indented as the node was
				$indentation = array_pop($leading);
				$moved = self::indentComments($moved, $indentation);
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
			$ends = $previous->trailingTrivia; // a copy: a private(set) array takes no indirect change from outside
			if ($ends && end($ends)->isEndOfLine()) {
				$previous->removeTrailingWhitespace(); // the line ends here, so nothing may dangle before it
			}
		}

		if ($next) {
			$next->setLeadingTrivia([...$leading, ...$after, ...$trailing, ...$next->leadingTrivia]);
		} elseif ($previous) {
			$previous->setTrailingTrivia([...$previous->trailingTrivia, ...$leading, ...$after]);
		}

		$parent->removeItem($this);
	}


	/** The node printed back to source, the trivia on its outer edges included; `$text` leaves them out. */
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
	 * Copies of the children of a list, adopted by the copy; a list has no slots to copy by.
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
			throw self::describeOwned($value);
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
			throw self::describeOwned($child);
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
	 * Refuses a node standing in a file before a factory building a new node of it moves anything: the factory
	 * may take a node out of a subtree without a file, never out of a live tree. A node given twice, or inside
	 * another one given, would be taken from the place the factory has just put it in, so it is refused too.
	 */
	protected static function checkDetached(?self ...$nodes): void
	{
		$nodes = array_values(array_filter($nodes));
		foreach ($nodes as $i => $node) {
			if ($node->getFile() !== null) {
				throw self::describeOwned($node);
			}

			foreach (array_slice($nodes, $i + 1) as $other) {
				if ($node->contains($other) || $other->contains($node)) {
					throw new \LogicException('A node cannot be two parts of the node a factory builds; a copy comes from withoutEdgeTrivia().');
				}
			}
		}
	}


	/** Whether the node is this one or stands anywhere inside it. */
	private function contains(self $node): bool
	{
		for ($ancestor = $node; $ancestor !== null; $ancestor = $ancestor->parent) {
			if ($ancestor === $this) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Puts the node under another one, or takes it out of the tree with null.
	 * @internal only the tree and the parser write the parent
	 */
	public function attachTo(?self $parent): void
	{
		$this->parent = $parent;
	}


	/**
	 * Tells the file that the children changed: every `adopt()` and `release()` since the last call is in place.
	 */
	protected function structureChanged(): void
	{
		$this->getFile()?->structureChanged();
	}


	/**
	 * Gives the node the leading indentation of the model, where the model starts a line and the node
	 * carries no leading trivia of its own.
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
	 * Returns the list with `$remove` items at `$index` replaced by `$insert`.
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


	private static function describeOwned(self|Token $value): \LogicException
	{
		return new \LogicException($value instanceof Token
			? 'The token already belongs to a tree; a copy comes from clone.'
			: 'The node already belongs to a tree; a copy comes from withoutEdgeTrivia(), or from clone with the trivia on its edges.');
	}


	/**
	 * Separates comments, each with the line ending directly after it, from the rest of the trivia.
	 * @param  list<Trivia>  $trivias
	 * @return array{list<Trivia>, list<Trivia>}  [rest, comments]
	 */
	private static function splitComments(array $trivias, bool $atLineStart): array
	{
		$rest = $comments = [];
		foreach ($trivias as $i => $trivia) {
			$before = $trivias[$i - 1] ?? null;
			if ($trivia->isComment()) {
				$opensLine = ($trivias[$i - 2] ?? null)?->isEndOfLine() ?? $atLineStart;
				if ($opensLine && $before?->kind === TriviaKind::Whitespace) {
					array_pop($rest); // the comment stands at the start of a line and the whitespace indents it
					$comments[] = $before;
				}

				$comments[] = $trivia;

			} elseif ($trivia->kind === TriviaKind::EndOfLine && $before?->isComment()) {
				$comments[] = $trivia;

			} else {
				$rest[] = $trivia;
			}
		}

		return [$rest, $comments];
	}


	/**
	 * Gives the indentation to each comment that does not start with one: a comment that ended a line and now
	 * opens one, as `splitComments()` hands it over without the whitespace before it.
	 * @param  list<Trivia>  $comments
	 * @return list<Trivia>
	 */
	private static function indentComments(array $comments, Trivia $indentation): array
	{
		$result = [];
		foreach ($comments as $i => $trivia) {
			if ($trivia->isComment() && ($comments[$i - 1] ?? null)?->kind !== TriviaKind::Whitespace) {
				$result[] = $indentation;
			}

			$result[] = $trivia;
		}

		return $result;
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
