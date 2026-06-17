<?php declare(strict_types=1);

namespace PhpSyntax;


/**
 * Whitespace, comment or open tag attached to a token: an immutable value, so the same instance may sit
 * on several tokens. A mutation that names one finds it among the trivia by identity.
 */
final readonly class Trivia
{
	public function __construct(
		public TriviaKind $kind,
		public string $text,
		/** inside string interpolation, where whitespace is part of the string value */
		public bool $inInterpolation = false,
		/** line in the original file; null for trivia created by a mutation */
		public ?int $originalLine = null,
	) {
	}


	public function isComment(): bool
	{
		return $this->kind === TriviaKind::Comment || $this->kind === TriviaKind::DocComment;
	}


	public function isDocComment(): bool
	{
		return $this->kind === TriviaKind::DocComment;
	}


	/** A comment that ends with its line, written with // or #. */
	public function isLineComment(): bool
	{
		return $this->kind === TriviaKind::Comment && !str_starts_with($this->text, '/*');
	}


	/** Whether the comment spans more than one line. */
	public function isMultiLine(): bool
	{
		return $this->isComment() && preg_match('~[\r\n]~', $this->text) === 1;
	}


	/**
	 * The text of the comment without its delimiters and without the * that opens the lines of a block comment,
	 * trimmed at both ends: the tokenizer counts the spaces before the line break as part of a // comment, and
	 * they are not text of the comment. What a line holds inside it keeps its own indentation.
	 * @return string  '' for a trivia that is not a comment
	 */
	public function getCommentText(): string
	{
		if (!$this->isComment()) {
			return '';
		} elseif ($this->isLineComment()) {
			return trim(substr($this->text, $this->text[0] === '#' ? 1 : 2));
		}

		$text = substr($this->text, $this->kind === TriviaKind::DocComment ? 3 : 2, -2);
		$lines = preg_split('~\r\n|\r|\n~', $text);
		foreach ($lines as $i => $line) {
			// one * opens a line, with one blank after it; a second one is text (a bullet, an emphasis)
			$lines[$i] = $i === 0 ? ltrim($line) : (string) preg_replace('~^[ \t]*(?:\*[ \t]?)?~', '', $line);
		}

		return trim(implode("\n", $lines));
	}


	/** Whitespace or a line ending. */
	public function isWhitespace(): bool
	{
		return $this->kind === TriviaKind::Whitespace || $this->kind === TriviaKind::EndOfLine;
	}


	/** Ends the line: a line ending, or an open tag whose text ends with one. */
	public function isEndOfLine(): bool
	{
		return $this->kind === TriviaKind::EndOfLine
			|| ($this->kind === TriviaKind::OpenTag && preg_match('~[\r\n]$~', $this->text) === 1);
	}
}
