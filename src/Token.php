<?php declare(strict_types=1);

namespace PhpSyntax;

use function is_int, ord;


final class Token implements \Stringable
{
	/** The node the token belongs to; only the tree writes it, through attachTo(). */
	public private(set) ?Node $parent = null;

	/** @internal position in the order of the tokens of the file, written by TokenIndex */
	public int $index = 0;

	/** @internal the index that numbered the token last: a shortcut to the file, verified before use */
	public ?TokenIndex $indexedBy = null;

	/**
	 * Whitespace, comments and the open tag before the token, up to the end of the line above it;
	 * setLeadingTrivia() writes it.
	 * @var list<Trivia>
	 */
	public private(set) array $leadingTrivia = [];

	/**
	 * What follows the token up to and including the end of its line; setTrailingTrivia() writes it.
	 * @var list<Trivia>
	 */
	public private(set) array $trailingTrivia = [];


	public function __construct(
		public int $kind,
		/** The text of the token, trivia excluded; setText() writes it. */
		public private(set) string $text,
		/** position in the original source; null for a token that did not come from the lexer */
		public readonly ?int $originalOffset = null,
		public readonly ?int $originalLine = null,
	) {
	}


	/**
	 * Replaces the text of the token, the trivia around it untouched; the file learns how many line endings
	 * the token gained or lost, so that the lines after it stay right.
	 */
	public function setText(string $text): void
	{
		$file = $this->getFile();
		$lineEndings = $file ? TokenIndex::countLineEndings($text) - TokenIndex::countLineEndings($this->text) : 0;
		$this->text = $text;
		$file?->tokenChanged($this, $lineEndings, leading: false);
	}


	/** @param list<Trivia> $trivia */
	public function setLeadingTrivia(array $trivia): void
	{
		$file = $this->getFile();
		$lineEndings = $file ? TokenIndex::countLineEndingsIn($trivia) - TokenIndex::countLineEndingsIn($this->leadingTrivia) : 0;
		$this->leadingTrivia = $trivia;
		$file?->tokenChanged($this, $lineEndings, leading: true);
	}


	/** @param list<Trivia> $trivia */
	public function setTrailingTrivia(array $trivia): void
	{
		$file = $this->getFile();
		$lineEndings = $file ? TokenIndex::countLineEndingsIn($trivia) - TokenIndex::countLineEndingsIn($this->trailingTrivia) : 0;
		$this->trailingTrivia = $trivia;
		$file?->tokenChanged($this, $lineEndings, leading: false);
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


	/** Navigation and positions come from the file index; a token of a detached subtree has none. */
	public function getNext(): ?self
	{
		return $this->findIndex()?->getNext($this);
	}


	public function getPrevious(): ?self
	{
		return $this->findIndex()?->getPrevious($this);
	}


	/** Current line, 1-based; unlike originalLine it follows mutations. */
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
	 * Puts the token under the node, or takes it out of the tree with null.
	 * @internal called by Node::adopt() and Node::release()
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
}
