<?php declare(strict_types=1);

namespace PhpSyntax;

use function is_int;


final class Token implements \Stringable
{
	/** @var list<Trivia> */
	public array $leadingTrivia = [];

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


	public function setText(string $text): void
	{
		$this->text = $text;
	}


	/** @param list<Trivia> $trivia */
	public function setLeadingTrivia(array $trivia): void
	{
		$this->leadingTrivia = $trivia;
	}


	/** @param list<Trivia> $trivia */
	public function setTrailingTrivia(array $trivia): void
	{
		$this->trailingTrivia = $trivia;
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
