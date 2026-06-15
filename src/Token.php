<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;

use function count;


/**
 * A token of the source with the trivia around it. The kind is the id of `PhpToken` (`Token::Variable`), which only
 * the lexer writes, and the text is written by `setText()`, which tells the index; `line` and `pos` say where
 * the token stood in the source it was read from, -1 for a token made otherwise. The language does not guard the
 * properties inherited from `PhpToken`, so writing them directly is not supported, and the parent is no part of
 * the API but for `is()`: the inherited `tokenize()` gives the raw tokens of PHP, `Lexer::tokenize()` those of the
 * library.
 */
final class Token extends \PhpToken implements \Stringable
{
	use TokenData;

	/** The node the token belongs to; only the tree writes it, public so that a node writes it without a call. */
	public ?Node $parent = null;

	/** @internal position in the order of the tokens of the file, written by TokenIndex */
	public int $ordinal = 0;

	/** @internal the index that numbered the token last: a shortcut to the file, verified before use */
	public ?TokenIndex $indexedBy = null;

	/**
	 * Whitespace, comments and the open tag before the token, up to the end of the line above it;
	 * `setLeadingTrivia()` writes it.
	 * @var list<Trivia>
	 */
	public private(set) array $leadingTrivia = [];

	/**
	 * What follows the token up to and including the end of its line; `setTrailingTrivia()` writes it.
	 * @var list<Trivia>
	 */
	public private(set) array $trailingTrivia = [];

	/** Line the token stands on now, 1-based; unlike `line` it follows the mutations. Null in a subtree without a file. */
	public ?int $currentLine {
		get => $this->findIndex()?->getLine($this);
	}

	/** Column the token stands on now, 1-based, in UTF-8 characters; `getVisualColumn()` expands the tabs. */
	public ?int $currentColumn {
		get => $this->findIndex()?->getColumn($this);
	}

	/** Byte offset of the token in the current text of the file; unlike `pos` it follows the mutations. */
	public ?int $currentOffset {
		get => $this->findIndex()?->getOffset($this);
	}


	/**
	 * The token the lexer reads the text as, written without an open tag and without trivia; a text read as anything
	 * but one token is refused. It runs the lexer, so the kind is the one PHP gives the text by itself.
	 */
	public static function fromText(string $text): static
	{
		return Lexer::readToken($text)
			?? throw new \InvalidArgumentException(Helpers::formatCode($text) . ' is not a single token.');
	}


	/**
	 * Replaces the text of the token, the trivia around it untouched; the file learns how many line endings
	 * the token gained or lost, so that the lines after it stay right.
	 */
	public function setText(string $text): static
	{
		if ($text === $this->text) {
			return $this;
		}

		$file = $this->getFile();
		$lineEndings = $file ? TokenIndex::countLineEndings($text) - TokenIndex::countLineEndings($this->text) : 0;
		$this->text = $text;
		$file?->tokenChanged($this, $lineEndings, leading: false);
		return $this;
	}


	/**
	 * Writes the trivia as they are given, leaving it to the caller that they stand where the lexer would put them,
	 * a line ending with the token whose line it ends; the helpers writing whitespace see to that themselves.
	 * @param list<Trivia> $trivia
	 */
	public function setLeadingTrivia(array $trivia): static
	{
		if ($trivia === $this->leadingTrivia) {
			return $this;
		}

		$file = $this->getFile();
		$lineEndings = $file ? TokenIndex::countLineEndingsIn($trivia) - TokenIndex::countLineEndingsIn($this->leadingTrivia) : 0;
		$this->leadingTrivia = $trivia;
		$file?->tokenChanged($this, $lineEndings, leading: true);
		return $this;
	}


	/**
	 * Writes the trivia as they are given, the way `setLeadingTrivia()` does.
	 * @param list<Trivia> $trivia
	 */
	public function setTrailingTrivia(array $trivia): static
	{
		if ($trivia === $this->trailingTrivia) {
			return $this;
		}

		$file = $this->getFile();
		$lineEndings = $file ? TokenIndex::countLineEndingsIn($trivia) - TokenIndex::countLineEndingsIn($this->trailingTrivia) : 0;
		$this->trailingTrivia = $trivia;
		$file?->tokenChanged($this, $lineEndings, leading: false);
		return $this;
	}


	/**
	 * Replaces this token in its parent by another, which is how the kind changes, `setText()` writing the same
	 * kind alone; the trivia around the old token stay around the new one, and where the new one then stands right
	 * against a token it would be read together with, a space keeps the two apart.
	 */
	public function replaceWith(self $token): void
	{
		Surgery::replace($this, $token);
	}


	public function getFile(): ?Nodes\FileNode
	{
		return $this->parent?->getFile();
	}


	/**
	 * The innermost node of the class the token stands in, its parent first.
	 * @template T of object
	 * @param  class-string<T>  $class
	 * @return (T&Node)|null
	 */
	public function findAncestor(string $class): ?Node
	{
		for ($node = $this->parent; $node; $node = $node->parent) {
			if ($node instanceof $class) {
				return $node;
			}
		}

		return null;
	}


	/** A token is its own first and last token, so `Node|Token` is handled by one call. */
	public function getFirstToken(): static
	{
		return $this;
	}


	/** A token is its own first and last token, so `Node|Token` is handled by one call. */
	public function getLastToken(): static
	{
		return $this;
	}


	/** The next token in the file; null at its end and in a detached subtree. */
	public function getNext(): ?self
	{
		return $this->findIndex()?->getNext($this);
	}


	public function getPrevious(): ?self
	{
		return $this->findIndex()?->getPrevious($this);
	}


	/** Whether the token stands before the other one in the file both are in. */
	public function isBefore(self $other): bool
	{
		$index = $this->findIndex() ?? throw new \LogicException('A token without a file has no order.');
		if (!$index->contains($other)) {
			throw new \InvalidArgumentException('Token ' . Helpers::formatCode($other->text) . ' stands in another file than token ' . Helpers::formatCode($this->text) . '.');
		}

		return $index->getOrdinal($this) < $index->getOrdinal($other);
	}


	/** Current column with tabs expanded, 1-based. */
	public function getVisualColumn(Style $style): ?int
	{
		return $this->findIndex()?->getVisualColumn($this, $style);
	}


	/** The index of the file the token is in: the one that numbered it when it still holds it, else via the parents. */
	private function findIndex(): ?TokenIndex
	{
		$index = $this->indexedBy;
		return $index !== null && $index->contains($this)
			? $index
			: $this->getFile()?->getIndex();
	}


	/** Whether the token is the first on its line. */
	public function startsLine(): bool
	{
		foreach ($this->leadingTrivia as $trivia) {
			if ($trivia->isLineEnding()) {
				return true;
			}
		}

		$previous = $this->getPrevious();
		$before = $previous?->trailingTrivia;
		return $previous === null
			|| ($before ? end($before)->isLineEnding() : self::endsWithLineEnding($previous->text));
	}


	/** Whether the text ends its line by itself: a close tag, a heredoc start, inline HTML. */
	private static function endsWithLineEnding(string $text): bool
	{
		return $text !== '' && ($text[-1] === "\n" || $text[-1] === "\r");
	}


	/**
	 * Whether a single line ending follows the token, with nothing but whitespace between it and the next token; a text
	 * that ends its line by itself, as a close tag does, counts as one. False for the last token and for one without a file.
	 */
	public function isFollowedByLineEnding(): bool
	{
		$next = $this->getNext();
		if ($next === null || $this->hasCommentUpTo($next)) {
			return false;
		}

		$endings = (int) self::endsWithLineEnding($this->text);
		foreach ([...$this->trailingTrivia, ...$next->leadingTrivia] as $trivia) {
			$endings += (int) $trivia->isLineEnding();
		}

		return $endings === 1;
	}


	/**
	 * Whitespace between the token and the next one on the same line (`''` when they touch); null when a line
	 * ending or a comment follows, when the whitespace belongs to a string, when the token ends its line
	 * inside its own text (a close tag, a heredoc start) or when the next token opens with a line ending
	 * of its own (inline HTML, `__halt_compiler()` data).
	 */
	public function getTrailingSpace(): ?string
	{
		$space = '';
		foreach ($this->trailingTrivia as $trivia) {
			if ($trivia->id !== Trivia::Whitespace || $trivia->inInterpolation) {
				return null;
			}

			$space .= $trivia->text;
		}

		if (self::endsWithLineEnding($this->text)) {
			return null;
		}

		$next = $this->getNext();
		return $next !== null && $next->text !== '' && ($next->text[0] === "\n" || $next->text[0] === "\r") ? null : $space;
	}


	/**
	 * Replaces the whitespace between the token and the next one; only where `getTrailingSpace()` is not null.
	 */
	public function setTrailingSpace(string $space): static
	{
		Helpers::checkWhitespace($space);
		$this->refuseInterpolation();
		$current = $this->getTrailingSpace();
		if ($current === null) {
			throw new \LogicException('Token ' . Helpers::formatCode($this->text) . ' is followed by a line ending or a comment.');
		} elseif ($current === $space) {
			return $this;
		}

		return $this->setTrailingTrivia($space === '' ? [] : [new Trivia(Trivia::Whitespace, $space)]);
	}


	/**
	 * Indentation of the line the token is on, whether the token starts it or not; a line a heredoc closes on
	 * is indented by the whitespace its closing marker is written with.
	 */
	public function getLineIndentation(): string
	{
		$token = $this;
		while (!$token->startsLine()) {
			$token = $token->getPrevious() ?? throw new \LogicException('A token without a file has no line.');
		}

		return $token->id === self::EndHeredoc
			? substr($token->text, 0, strspn($token->text, " \t"))
			: $token->getIndentation();
	}


	/**
	 * Whitespace at the start of the token's line, before any comment sitting between it and the token;
	 * empty when the token does not start a line.
	 */
	public function getIndentation(): string
	{
		$indentation = '';
		foreach (array_slice($this->leadingTrivia, $this->findLineStart()) as $trivia) {
			if ($trivia->id !== Trivia::Whitespace) {
				break;
			}

			$indentation .= $trivia->text;
		}

		return $indentation;
	}


	/** Index of the first trivia after the last line ending or open tag. */
	private function findLineStart(): int
	{
		$start = 0;
		foreach ($this->leadingTrivia as $i => $trivia) {
			if ($trivia->isLineEnding() || $trivia->id === Trivia::OpenTag) {
				$start = $i + 1;
			}
		}

		return $start;
	}


	/**
	 * Replaces the whitespace at the start of the token's line, leaving a comment sitting between it
	 * and the token alone; the token must start a line.
	 */
	public function setIndentation(string $indentation): static
	{
		Helpers::checkWhitespace($indentation);
		$this->refuseInterpolation();
		if (!$this->startsLine()) {
			throw new \LogicException('Token ' . Helpers::formatCode($this->text) . ' does not start a line.');
		} elseif ($this->getIndentation() === $indentation) {
			return $this;
		}

		$leading = $this->leadingTrivia;
		$start = $this->findLineStart();
		$end = $start;
		while (($leading[$end] ?? null)?->id === Trivia::Whitespace) {
			$end++;
		}

		$replacement = $indentation === '' ? [] : [new Trivia(Trivia::Whitespace, $indentation)];
		return $this->setLeadingTrivia([...array_slice($leading, 0, $start), ...$replacement, ...array_slice($leading, $end)]);
	}


	/**
	 * Moves the token to its own line unless it already starts one; the new line has no indentation, which
	 * `setIndentation()` gives it.
	 * The line ending goes to the trailing trivia of the previous token, where the lexer would put it.
	 */
	public function ensureStartsLine(string $lineEnding): void
	{
		Helpers::checkLineEnding($lineEnding);
		$this->refuseInterpolation();
		if ($this->startsLine()) {
			return;
		}

		$previous = $this->getPrevious();
		if ($previous === null) {
			$this->setLeadingTrivia([new Trivia(Trivia::LineEnding, $lineEnding), ...$this->leadingTrivia]);
			return;
		}

		$previous->removeTrailingWhitespace();
		$previous->setTrailingTrivia([...$previous->trailingTrivia, new Trivia(Trivia::LineEnding, $lineEnding)]);
	}


	/**
	 * Removes the whitespace the trailing trivia end with, before the line ending or, where the line goes on,
	 * before the next token; comments and the line ending stay, and whitespace ending a single-line comment
	 * counts as well, since the tokenizer makes it part of the comment.
	 */
	public function removeTrailingWhitespace(): void
	{
		$this->refuseInterpolation();
		$trailing = [];
		$whitespace = [];
		foreach ($this->trailingTrivia as $trivia) {
			if ($trivia->id === Trivia::Whitespace) {
				$whitespace[] = $trivia;
			} elseif ($trivia->id === Trivia::LineEnding) {
				$trailing[] = $trivia;
				$whitespace = [];
			} else {
				$trailing = [...$trailing, ...$whitespace, $trivia];
				$whitespace = [];
			}
		}

		$last = $trailing ? $trailing[count($trailing) - 1] : null;
		$comment = $last?->isLineComment() ? $last : ($trailing[count($trailing) - 2] ?? null);
		if ($comment?->isLineComment() && rtrim($comment->text) !== $comment->text) {
			$trimmed = $comment->withText(rtrim($comment->text));
			$trailing = array_map(fn(Trivia $trivia) => $trivia === $comment ? $trimmed : $trivia, $trailing);
		}

		$this->setTrailingTrivia($trailing);
	}


	/** Whether a comment sits in the leading or trailing trivia of the token. */
	public function hasComment(): bool
	{
		// dresscode:ignore arrayFunctionForForeach -- a loop is faster than array_any() on this hot path
		foreach ($this->leadingTrivia as $trivia) {
			if ($trivia->isComment()) {
				return true;
			}
		}

		// dresscode:ignore arrayFunctionForForeach -- a loop is faster than array_any() on this hot path
		foreach ($this->trailingTrivia as $trivia) {
			if ($trivia->isComment()) {
				return true;
			}
		}

		return false;
	}


	/**
	 * The comments among the trivia of the token, the leading ones first.
	 * @return list<Trivia>
	 */
	public function getComments(): array
	{
		return [...$this->getLeadingComments(), ...$this->getTrailingComments()];
	}


	/** Whether a comment sits in the leading trivia of the token, before its text. */
	public function hasLeadingComment(): bool
	{
		// dresscode:ignore arrayFunctionForForeach -- a loop is faster than array_any() on this hot path
		foreach ($this->leadingTrivia as $trivia) {
			if ($trivia->isComment()) {
				return true;
			}
		}

		return false;
	}


	/** Whether a comment sits in the trailing trivia of the token, after its text up to the end of its line. */
	public function hasTrailingComment(): bool
	{
		// dresscode:ignore arrayFunctionForForeach -- a loop is faster than array_any() on this hot path
		foreach ($this->trailingTrivia as $trivia) {
			if ($trivia->isComment()) {
				return true;
			}
		}

		return false;
	}


	/**
	 * The comments among the leading trivia of the token.
	 * @return list<Trivia>
	 */
	public function getLeadingComments(): array
	{
		return array_values(array_filter($this->leadingTrivia, fn(Trivia $trivia) => $trivia->isComment()));
	}


	/**
	 * The comments among the trailing trivia of the token.
	 * @return list<Trivia>
	 */
	public function getTrailingComments(): array
	{
		return array_values(array_filter($this->trailingTrivia, fn(Trivia $trivia) => $trivia->isComment()));
	}


	/**
	 * Whether a comment sits anywhere between the text of this token and the text of the given one:
	 * in the trailing trivia here, the leading trivia there, or around any token between them. The tokens stand
	 * in one file, this one first; the same token twice is an empty interval.
	 */
	public function hasCommentUpTo(self $end): bool
	{
		if ($end !== $this && !$this->isBefore($end)) {
			throw new \InvalidArgumentException('Token ' . Helpers::formatCode($end->text) . ' stands before token ' . Helpers::formatCode($this->text) . ', which is where the interval starts.');
		}

		for ($token = $this; $token !== null; $token = $token->getNext()) {
			foreach ($token === $this ? [] : $token->leadingTrivia as $trivia) {
				if ($trivia->isComment()) {
					return true;
				}
			}

			if ($token === $end) {
				return false;
			}

			foreach ($token->trailingTrivia as $trivia) {
				if ($trivia->isComment()) {
					return true;
				}
			}
		}

		return false;
	}


	/**
	 * Removes one trivia of the token, tidying the whitespace around it: a comment alone on its line
	 * takes the line with it, an inline one takes one adjacent space.
	 */
	public function removeTrivia(Trivia $trivia): void
	{
		if ($trivia->inInterpolation) {
			throw new \LogicException('Trivia inside string interpolation cannot be removed.');
		}

		foreach ([true, false] as $isLeading) {
			$list = $isLeading ? $this->leadingTrivia : $this->trailingTrivia;
			$index = array_search($trivia, $list, strict: true);
			if ($index === false) {
				continue;
			}

			$from = $to = $index;
			$space = ($list[$index - 1] ?? null)?->id === Trivia::Whitespace;
			$before = $list[$index - ($space ? 2 : 1)] ?? null;
			$startsLine = $isLeading && ($before === null || $before->isLineEnding());
			$next = $list[$index + 1] ?? null;
			if ($startsLine && $next?->id === Trivia::LineEnding) {
				[$from, $to] = [$index - (int) $space, $index + 1]; // alone on its line: the indentation and the line ending go too
			} elseif ($space && !$startsLine) {
				$from = $index - 1;
			} elseif ($next?->id === Trivia::Whitespace) {
				$to = $index + 1; // no space before, or only the indentation, which stays: take the one after
			}

			$result = [...array_slice($list, 0, $from), ...array_slice($list, $to + 1)];
			$isLeading ? $this->setLeadingTrivia($result) : $this->setTrailingTrivia($result);
			return;
		}

		throw new \LogicException('The trivia does not belong to the token.');
	}


	/** Replaces one trivia of the token with another in place. */
	public function replaceTrivia(Trivia $old, Trivia $new): void
	{
		foreach ([true, false] as $isLeading) {
			$list = $isLeading ? $this->leadingTrivia : $this->trailingTrivia;
			$index = array_search($old, $list, strict: true);
			if ($index === false) {
				continue;
			}

			$list[$index] = $new;
			$isLeading ? $this->setLeadingTrivia($list) : $this->setTrailingTrivia($list);
			return;
		}

		throw new \LogicException('The trivia does not belong to the token.');
	}


	/**
	 * The number of blank lines before the token, above any comment before it, which is what
	 * `setBlankLinesBefore()` sets.
	 */
	public function countBlankLinesBefore(): int
	{
		$leading = $this->leadingTrivia;
		$start = $leading && $leading[0]->id === Trivia::OpenTag ? 1 : 0;
		$end = $start;
		while (($leading[$end] ?? null)?->id === Trivia::LineEnding) {
			$end++;
		}

		return $end - $start;
	}


	/**
	 * Sets the number of blank lines before the token, which must start a line; comments before it keep
	 * their position after the blank lines.
	 */
	public function setBlankLinesBefore(int $count, string $lineEnding): static
	{
		if ($count < 0) {
			throw new \InvalidArgumentException("Count of blank lines `$count` is negative.");
		}

		Helpers::checkLineEnding($lineEnding);
		$this->refuseInterpolation();
		if (!$this->startsLine()) {
			throw new \LogicException('Token ' . Helpers::formatCode($this->text) . ' does not start a line.');
		}

		$leading = $this->leadingTrivia;
		$start = $leading && $leading[0]->id === Trivia::OpenTag ? 1 : 0;
		$end = $start;
		while ($end < count($leading) && $leading[$end]->id === Trivia::LineEnding) {
			$end++;
		}

		$current = array_slice($leading, $start, $end - $start);
		if (count($current) === $count && array_all($current, fn(Trivia $trivia) => $trivia->text === $lineEnding)) {
			return $this;
		}

		$blank = array_fill(0, $count, new Trivia(Trivia::LineEnding, $lineEnding));
		return $this->setLeadingTrivia([...array_slice($leading, 0, $start), ...$blank, ...array_slice($leading, $end)]);
	}


	/**
	 * Copy without a parent and without the index that numbered the original, which would keep its whole file
	 * alive; the trivia are immutable, so the copy shares them.
	 */
	public function __clone()
	{
		$this->parent = null;
		$this->indexedBy = null;
		$this->ordinal = 0;
	}


	public function __toString(): string
	{
		return implode('', array_map(fn(Trivia $trivia) => $trivia->text, $this->leadingTrivia))
			. $this->text
			. implode('', array_map(fn(Trivia $trivia) => $trivia->text, $this->trailingTrivia));
	}


	/** Whitespace inside string interpolation can change what the string reads, so it must not be reformatted. */
	private function refuseInterpolation(): void
	{
		foreach ([...$this->leadingTrivia, ...$this->trailingTrivia] as $trivia) {
			if ($trivia->inInterpolation) {
				throw new \LogicException('Token ' . Helpers::formatCode($this->text) . ' is inside string interpolation; its whitespace cannot be changed.');
			}
		}
	}
}
