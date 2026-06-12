<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;

use function count, is_int, ord;


final class Token implements \Stringable
{
	/** The node the token belongs to; only the tree writes it, through `attachTo()`. */
	public private(set) ?Node $parent = null;

	/** @internal position in the order of the tokens of the file, written by TokenIndex */
	public int $index = 0;

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


	public function __construct(
		public readonly int $kind,
		/** The text of the token, trivia excluded; `setText()` writes it. */
		public private(set) string $text,
		/** Position in the original source; null for a token that did not come from the lexer */
		public readonly ?int $originalOffset = null,
		public readonly ?int $originalLine = null,
	) {
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


	/**
	 * Whether the token is of one of the kinds, given as a kind or as the text of an operator or punctuation;
	 * the content of a string never matches a text.
	 */
	public function is(int|string ...$kinds): bool
	{
		foreach ($kinds as $kind) {
			if (is_int($kind) ? $this->kind === $kind : ($this->text === $kind && !$this->isStringContent())) {
				return true;
			}
		}

		return false;
	}


	private function isStringContent(): bool
	{
		return $this->kind === TokenKind::EncapsedAndWhitespace
			|| $this->kind === TokenKind::ConstantEncapsedString
			|| $this->kind === TokenKind::InlineHtml
			|| $this->kind === TokenKind::NumericString
			|| $this->kind === TokenKind::StringVariableName
			|| $this->kind === TokenKind::HaltCompilerData;
	}


	/** A semicolon or a close tag standing in for it. */
	public function isSemicolon(): bool
	{
		return $this->kind === ord(';') || $this->kind === TokenKind::CloseTag;
	}


	public function isOpenTagWithEcho(): bool
	{
		return $this->kind === TokenKind::OpenTagWithEcho;
	}


	public function getFile(): ?Nodes\FileNode
	{
		return $this->parent?->getFile();
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


	/** Current line, 1-based; unlike `originalLine` it follows mutations. */
	public function getLine(): ?int
	{
		return $this->findIndex()?->getLine($this);
	}


	/** Current column, 1-based, in UTF-8 characters. */
	public function getColumn(): ?int
	{
		return $this->findIndex()?->getColumn($this);
	}


	public function getOffset(): ?int
	{
		return $this->findIndex()?->getOffset($this);
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
			if ($trivia->isEndOfLine()) {
				return true;
			}
		}

		$previous = $this->getPrevious();
		$before = $previous?->trailingTrivia;
		return $previous === null
			|| ($before ? end($before)->isEndOfLine() : self::endsWithLineEnding($previous->text));
	}


	/** Whether the text ends its line by itself: a close tag, a heredoc start, inline HTML. */
	private static function endsWithLineEnding(string $text): bool
	{
		return $text !== '' && ($text[-1] === "\n" || $text[-1] === "\r");
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
			if ($trivia->kind !== TriviaKind::Whitespace || $trivia->inInterpolation) {
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
		$this->refuseInterpolation();
		if ($this->getTrailingSpace() === null) {
			throw new \LogicException("Token '$this->text' is followed by a line ending or a comment.");
		}

		return $this->setTrailingTrivia($space === '' ? [] : [new Trivia(TriviaKind::Whitespace, $space)]);
	}


	/** Indentation of the line the token is on, whether the token starts it or not. */
	public function getLineIndentation(): string
	{
		$token = $this;
		while (!$token->startsLine()) {
			$token = $token->getPrevious() ?? throw new \LogicException('A token without a file has no line.');
		}

		return $token->getIndentation();
	}


	/**
	 * Whitespace at the start of the token's line, before any comment sitting between it and the token;
	 * empty when the token does not start a line.
	 */
	public function getIndentation(): string
	{
		$indentation = '';
		foreach (array_slice($this->leadingTrivia, $this->findLineStart()) as $trivia) {
			if ($trivia->kind !== TriviaKind::Whitespace) {
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
			if ($trivia->isEndOfLine() || $trivia->kind === TriviaKind::OpenTag) {
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
		$this->refuseInterpolation();
		if (!$this->startsLine()) {
			throw new \LogicException("Token '$this->text' does not start a line.");
		}

		$leading = $this->leadingTrivia;
		$start = $this->findLineStart();
		$end = $start;
		while (($leading[$end] ?? null)?->kind === TriviaKind::Whitespace) {
			$end++;
		}

		$replacement = $indentation === '' ? [] : [new Trivia(TriviaKind::Whitespace, $indentation)];
		return $this->setLeadingTrivia([...array_slice($leading, 0, $start), ...$replacement, ...array_slice($leading, $end)]);
	}


	/**
	 * Moves the token to its own line unless it already starts one; the new line has no indentation, which
	 * `setIndentation()` gives it.
	 * The line ending goes to the trailing trivia of the previous token, where the lexer would put it.
	 */
	public function ensureLeadingNewline(string $eol = "\n"): void
	{
		$this->refuseInterpolation();
		if ($this->startsLine()) {
			return;
		}

		$previous = $this->getPrevious();
		if ($previous === null) {
			$this->setLeadingTrivia([new Trivia(TriviaKind::EndOfLine, $eol), ...$this->leadingTrivia]);
			return;
		}

		$previous->removeTrailingWhitespace();
		$previous->setTrailingTrivia([...$previous->trailingTrivia, new Trivia(TriviaKind::EndOfLine, $eol)]);
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
			if ($trivia->kind === TriviaKind::Whitespace) {
				$whitespace[] = $trivia;
			} elseif ($trivia->kind === TriviaKind::EndOfLine) {
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
			$trimmed = new Trivia(TriviaKind::Comment, rtrim($comment->text), $comment->inInterpolation);
			$trailing = array_map(fn(Trivia $trivia) => $trivia === $comment ? $trimmed : $trivia, $trailing);
		}

		$this->setTrailingTrivia($trailing);
	}


	/** Whether a comment sits in the leading or trailing trivia of the token. */
	public function hasComment(): bool
	{
		return $this->getComments() !== [];
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
	 * Whether a comment sits anywhere between the text of this token and the text of the given one:
	 * in the trailing trivia here, the leading trivia there, or around any token between them.
	 */
	public function hasCommentUpTo(self $end): bool
	{
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
	 * Sets the number of blank lines before the token, which must start a line; comments before it keep
	 * their position after the blank lines.
	 */
	public function setBlankLinesBefore(int $count, string $eol = "\n"): static
	{
		$this->refuseInterpolation();
		if (!$this->startsLine()) {
			throw new \LogicException("Token '$this->text' does not start a line.");
		}

		$leading = $this->leadingTrivia;
		$start = $leading && $leading[0]->kind === TriviaKind::OpenTag ? 1 : 0;
		$end = $start;
		while ($end < count($leading) && $leading[$end]->kind === TriviaKind::EndOfLine) {
			$end++;
		}

		$blank = array_fill(0, $count, new Trivia(TriviaKind::EndOfLine, $eol));
		return $this->setLeadingTrivia([...array_slice($leading, 0, $start), ...$blank, ...array_slice($leading, $end)]);
	}


	/**
	 * Puts the token under the node, or takes it out of the tree with null.
	 * @internal only the tree and the parser write the parent
	 */
	public function attachTo(?Node $parent): void
	{
		$this->parent = $parent;
	}


	/** Copy without a parent; the trivia are immutable, so the copy shares them. */
	public function __clone()
	{
		$this->parent = null;
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
				throw new \LogicException("Token '$this->text' is inside string interpolation; its whitespace cannot be changed.");
			}
		}
	}
}
