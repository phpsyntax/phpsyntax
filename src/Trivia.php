<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;

use function count, strlen;


/**
 * Whitespace, comment or open tag attached to a token, the tokens PHP ignores. The kind is the id of `PhpToken`
 * (`Trivia::Comment`), and `line` and `pos` say where the trivia stood in the source it was read from, -1 for
 * a trivia made otherwise. A trivia is never changed once made, so the same instance may sit on several tokens;
 * a mutation that names one finds it among the trivia by identity. Only the contract guards that: the properties
 * inherited from `PhpToken` are writable, and writing them is not supported; `withText()` gives a changed copy.
 */
final class Trivia extends \PhpToken
{
	public const
		Whitespace = T_WHITESPACE,
		Comment = T_COMMENT,
		DocComment = T_DOC_COMMENT,
		OpenTag = T_OPEN_TAG;

	/** a line ending, which PHP reads as a part of `T_WHITESPACE` and the lexer splits out; far from the kinds `Token` has of its own */
	public const LineEnding = -100;

	/** inside string interpolation, where whitespace can change what the string reads: `"${a}"` and `"${a }"` differ; only the lexer writes it */
	public bool $inInterpolation = false;


	/**
	 * The trivia of the text: whitespace within a line, one line ending, a comment of one line written with `//` or `#`,
	 * a block comment, a doc comment, or an open tag with the whitespace after it; anything else is refused.
	 */
	public static function fromText(string $text): static
	{
		if (preg_match('~^[ \t]+$~D', $text)) {
			return new self(self::Whitespace, $text);
		} elseif ($text === "\n" || $text === "\r\n" || $text === "\r") {
			return new self(self::LineEnding, $text);
		} elseif (preg_match('~^(<\?php(?:[ \t\n]|\r\n?)|<\?)$~Di', $text)) {
			return new self(self::OpenTag, $text);
		}

		$pieces = @\PhpToken::tokenize('<?php ' . $text); // @ - a comment left open
		$unclosed = str_starts_with($text, '/*') && (strlen($text) < 4 || !str_ends_with($text, '*/'));
		$kind = count($pieces) === 2 && $pieces[1]->text === $text && !$unclosed ? $pieces[1]->id : null;
		return $kind === self::Comment || $kind === self::DocComment
			? new self($kind, $text)
			: throw new \InvalidArgumentException(Helpers::formatCode($text) . ' is not whitespace, a line ending, a comment or an open tag.');
	}


	/** A copy with another text, standing where this one stood. */
	public function withText(string $text): self
	{
		$trivia = clone $this;
		$trivia->text = $text;
		return $trivia;
	}


	public function isComment(): bool
	{
		return $this->id === self::Comment || $this->id === self::DocComment;
	}


	/** A comment that ends with its line, written with `//` or `#`. */
	public function isLineComment(): bool
	{
		return $this->id === self::Comment && !str_starts_with($this->text, '/*');
	}


	/** Whether the comment spans more than one line. */
	public function isMultiLineComment(): bool
	{
		return $this->isComment() && preg_match('~[\r\n]~', $this->text) === 1;
	}


	/**
	 * The text of the comment without its delimiters and without the `*` that opens the lines of a block comment,
	 * trimmed at both ends: the tokenizer counts the spaces before the line break as part of a `//` comment, and
	 * they are not text of the comment. What a line holds inside it keeps its own indentation.
	 * @return string  `''` for a trivia that is not a comment
	 */
	public function getCommentText(): string
	{
		if (!$this->isComment()) {
			return '';
		} elseif ($this->isLineComment()) {
			return trim(substr($this->text, $this->text[0] === '#' ? 1 : 2));
		}

		$text = substr($this->text, $this->id === self::DocComment ? 3 : 2, -2);
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
		return $this->id === self::Whitespace || $this->id === self::LineEnding;
	}


	/** Ends the line: a line ending, or an open tag whose text ends with one. */
	public function isLineEnding(): bool
	{
		return $this->id === self::LineEnding
			|| ($this->id === self::OpenTag && preg_match('~[\r\n]$~', $this->text) === 1);
	}


	public function isIgnorable(): bool
	{
		return true;
	}


	/** A line ending is `T_WHITESPACE` to PHP. */
	public function getTokenName(): ?string
	{
		return $this->id === self::LineEnding ? 'T_WHITESPACE' : parent::getTokenName();
	}
}
