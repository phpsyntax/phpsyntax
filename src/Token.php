<?php declare(strict_types=1);

namespace PhpSyntax;

use function is_int;


final class Token implements \Stringable
{
	/** The node the token belongs to; only the tree writes it, through attachTo(). */
	public private(set) ?Node $parent = null;

	/** @var list<Trivia> */
	public array $leadingTrivia = [];

	/**
	 * What follows the token up to and including the end of its line; setTrailingTrivia() writes it.
	 * @var list<Trivia>
	 */
	public private(set) array $trailingTrivia = [];

	/** @internal position in the order of the tokens of the file, written by TokenIndex */
	public int $index = 0;

	/** @internal the index that numbered the token last: a shortcut to the file, verified before use */
	public ?TokenIndex $indexedBy = null;


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
	 * Puts the token under the node, or takes it out of the tree with null.
	 * @internal called by Node::adopt() and Node::release()
	 */
	public function attachTo(?Node $parent): void
	{
		$this->parent = $parent;
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


	public function getFile(): ?Nodes\FileNode
	{
		return $this->parent?->getFile();
	}


	/** Navigation and positions come from the file index; a token of a detached subtree has none. */
	public function getNext(): ?self
	{
		return $this->getFile()?->getIndex()->getNext($this);
	}


	public function getPrevious(): ?self
	{
		return $this->getFile()?->getIndex()->getPrevious($this);
	}


	/** Current line, 1-based; unlike originalLine it follows mutations. */
	public function getLine(): ?int
	{
		return $this->getFile()?->getIndex()->getLine($this);
	}


	/** Current column, 1-based, in UTF-8 characters. */
	public function getColumn(): ?int
	{
		return $this->getFile()?->getIndex()->getColumn($this);
	}


	/** Current column with tabs expanded, 1-based. */
	public function getVisualColumn(Style $style): ?int
	{
		return $this->getFile()?->getIndex()->getVisualColumn($this, $style);
	}


	public function getOffset(): ?int
	{
		return $this->getFile()?->getIndex()->getOffset($this);
	}


	public function __toString(): string
	{
		return implode('', array_map(fn(Trivia $trivia) => $trivia->text, $this->leadingTrivia))
			. $this->text
			. implode('', array_map(fn(Trivia $trivia) => $trivia->text, $this->trailingTrivia));
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
}
