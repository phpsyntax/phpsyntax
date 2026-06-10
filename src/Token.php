<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;


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

	/** Column the token stands on now, 1-based, in UTF-8 characters. */
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
		$file = $this->getFile();
		$lineEndings = $file ? TokenIndex::countLineEndings($text) - TokenIndex::countLineEndings($this->text) : 0;
		$this->text = $text;
		$file?->tokenChanged($this, $lineEndings, leading: false);
		return $this;
	}


	/** @param list<Trivia> $trivia */
	public function setLeadingTrivia(array $trivia): static
	{
		$file = $this->getFile();
		$lineEndings = $file ? TokenIndex::countLineEndingsIn($trivia) - TokenIndex::countLineEndingsIn($this->leadingTrivia) : 0;
		$this->leadingTrivia = $trivia;
		$file?->tokenChanged($this, $lineEndings, leading: true);
		return $this;
	}


	/** @param list<Trivia> $trivia */
	public function setTrailingTrivia(array $trivia): static
	{
		$file = $this->getFile();
		$lineEndings = $file ? TokenIndex::countLineEndingsIn($trivia) - TokenIndex::countLineEndingsIn($this->trailingTrivia) : 0;
		$this->trailingTrivia = $trivia;
		$file?->tokenChanged($this, $lineEndings, leading: false);
		return $this;
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
		$comments = [];
		foreach ([...$this->leadingTrivia, ...$this->trailingTrivia] as $trivia) {
			if ($trivia->isComment()) {
				$comments[] = $trivia;
			}
		}

		return $comments;
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
}
