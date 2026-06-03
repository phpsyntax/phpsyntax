<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;

use function count, ord, strlen;
use const PHP_VERSION_ID, PREG_SPLIT_DELIM_CAPTURE, PREG_SPLIT_NO_EMPTY;


/**
 * Turns source code into significant tokens; whitespace, comments and open tags become trivia.
 * Trailing trivia of a token reaches up to and including the end of its line, everything else
 * is leading trivia of the next token; an open tag always starts leading trivia, and the final
 * `EndOfFile` token carries only leading trivia.
 */
final class Lexer
{
	/** kinds of the tokens that open or close a string, a heredoc or a shell command: `"`, `` ` `` and the rest */
	private const StringDelimiters = [
		0x22 => true,
		0x60 => true,
		Token::StartHeredoc => true,
		Token::EndHeredoc => true,
		Token::EncapsedAndWhitespace => true,
	];

	/** @var list<Emulator> */
	private array $emulators;


	/** @param ?list<Emulator> $emulators  null = those needed by the running PHP version; a list of its own is for the tests of the emulators */
	public function __construct(?array $emulators = null)
	{
		$this->emulators = $emulators ?? self::createHostEmulators();
	}


	/**
	 * Emulators of syntax the running PHP version does not tokenize itself.
	 * @return list<Emulator>
	 * @internal
	 */
	public static function createHostEmulators(): array
	{
		$emulators = [];
		if (PHP_VERSION_ID < 80500) {
			$emulators[] = new Emulators\PipeOperator;
			$emulators[] = new Emulators\VoidCast;
		}

		return $emulators;
	}


	/**
	 * @param  bool  $withPositions  false for a fragment whose tokens have no place in an original file
	 * @return list<Token>
	 */
	public function tokenize(string $code, bool $withPositions = true): array
	{
		return self::foldTrivia($this->split($code), $code, $withPositions);
	}


	/**
	 * The token the text, written without an open tag, is read as by itself, without trivia; null for a text
	 * read as anything else: several tokens, a comment or whitespace, code the lexer refuses.
	 * @internal
	 */
	public static function readToken(string $text): ?Token
	{
		static $lexer = new self;
		$pieces = $lexer->split('<?php ' . $text);
		if (count($pieces) !== 2) {
			return null;
		}

		$kind = $pieces[1]->id;
		return $pieces[1]->text === $text
			&& !isset(self::StringDelimiters[$kind]) // a string left open, which the lexer refuses
			&& !in_array($kind, [Trivia::Whitespace, Trivia::Comment, Trivia::DocComment, T_BAD_CHARACTER], true)
				? new Token($kind, $text)
				: null;
	}


	/**
	 * The tokens of PHP the code is read as, with the syntax the emulators add.
	 * @return array<int, Token>
	 */
	private function split(string $code): array
	{
		$pieces = @Token::tokenize($code); // @ an invalid escape sequence in a string triggers a warning
		foreach ($this->emulators as $emulator) {
			if ($emulator->isNeeded($code)) {
				$pieces = $emulator->emulate($pieces);
			}
		}

		return $pieces;
	}


	/**
	 * Keeps the significant pieces of the tokenizer output as tokens and makes trivia of the rest, in one pass.
	 * The trivia are written without the setters, the tokens standing nowhere yet, so the pass runs in the scope
	 * of `Token`, the only one that may write them.
	 * @param  array<int, Token>  $pieces
	 * @return list<Token>
	 */
	private static function foldTrivia(array $pieces, string $code, bool $withPositions): array
	{
		$delimiters = self::StringDelimiters;
		$trackString = self::trackString(...);
		$describeString = self::describeString(...);
		$splitWhitespace = self::splitWhitespace(...);
		$fold = \Closure::bind(static function () use ($pieces, $code, $withPositions, $delimiters, $trackString, $describeString, $splitWhitespace): array {
			$tokens = [];
			$leading = []; // trivia for the next token
			$open = null; // token whose line is still open for trailing trivia
			$trailing = []; // trailing trivia of the open token, written to it once its line closes or the next token comes
			$braces = []; // brace nesting inside string interpolation
			$halt = 0; // 1 = after __halt_compiler, 2 = its data follow
			$string = null; // kind, text and offset of the token opening the string, the heredoc or the shell command the tokens stand in

			foreach ($pieces as $piece) {
				$kind = $piece->id;
				if ($kind === T_BAD_CHARACTER) {
					throw new ParseException(sprintf('Unexpected character 0x%02X', ord($piece->text)), $piece->line, $piece->pos, $code);
				}

				$text = $piece->text;
				$line = $withPositions ? $piece->line : -1;
				$pos = $withPositions ? $piece->pos : -1;
				$inInterpolation = $braces !== [];
				if (!$inInterpolation && isset($delimiters[$kind])) {
					$string = $trackString($string, $kind, $text, $piece->pos, $code);
				}

				if ($kind === Trivia::Whitespace) {
					foreach ($splitWhitespace($text) as $part) {
						$isEol = $part[0] === "\n" || $part[0] === "\r";
						$trivia = new Trivia($isEol ? Trivia::LineEnding : Trivia::Whitespace, $part, $line, $pos);
						$trivia->inInterpolation = $inInterpolation;
						$line = $isEol && $line >= 0 ? $line + 1 : $line;
						$pos = $pos >= 0 ? $pos + strlen($part) : $pos;
						if (!$open) {
							$leading[] = $trivia;
						} elseif ($isEol) {
							$trailing[] = $trivia;
							$open->trailingTrivia = $trailing;
							$open = null;
							$trailing = [];
						} else {
							$trailing[] = $trivia;
						}
					}
					continue;

				} elseif ($kind === Trivia::Comment || $kind === Trivia::DocComment) {
					if (str_starts_with($text, '/*') && !str_ends_with($text, '*/')) {
						throw new ParseException('Unterminated comment', $piece->line, $piece->pos, $code);
					}

					$trivia = new Trivia($kind, $text, $line, $pos);
					$trivia->inInterpolation = $inInterpolation;
					if ($open) {
						$trailing[] = $trivia;
					} else {
						$leading[] = $trivia;
					}
					continue;

				} elseif ($kind === Trivia::OpenTag) {
					if ($open) {
						$open->trailingTrivia = $trailing;
						$open = null;
						$trailing = [];
					}

					$leading[] = new Trivia(Trivia::OpenTag, $text, $line, $pos);
					continue;

				} elseif ($kind === Token::HaltCompiler) {
					$halt = 1;

				} elseif (($kind === ord(';') || $kind === Token::CloseTag) && $halt === 1) {
					$halt = 2;

				} elseif ($kind === Token::InlineHtml && $halt === 2) {
					$piece->id = Token::HaltCompilerData;

				} elseif ($kind === Token::CurlyOpen || $kind === Token::DollarOpenCurlyBraces) {
					$braces[] = true;

				} elseif ($kind === ord('{') && $inInterpolation) {
					$braces[] = false;

				} elseif ($kind === ord('}') && $inInterpolation) {
					array_pop($braces);
				}

				if ($open && $trailing) {
					$open->trailingTrivia = $trailing;
					$trailing = [];
				}

				if (!$withPositions) {
					$piece->line = $piece->pos = -1;
				}

				if ($leading) {
					$piece->leadingTrivia = $leading;
					$leading = [];
				}

				$tokens[] = $piece;
				$end = $text[-1] ?? '';
				$open = $end === "\n" || $end === "\r" ? null : $piece;
			}

			if ($open && $trailing) {
				$open->trailingTrivia = $trailing;
			}

			if ($string) {
				throw new ParseException('Unterminated ' . $describeString($string[0], $string[1]), sourceOffset: $string[2], code: $code);
			}

			$last = $pieces[count($pieces) - 1] ?? null;
			$eof = $withPositions
				? new Token(Token::EndOfFile, '', $last ? $last->line + TokenIndex::countLineEndings($last->text) : 1, strlen($code))
				: new Token(Token::EndOfFile, '');
			$eof->leadingTrivia = $leading;
			$tokens[] = $eof;
			return $tokens;
		}, null, Token::class);

		return $fold();
	}


	/**
	 * The string still open after the token outside any interpolation, as the kind and the text of its opening
	 * token and its offset: a double quote, a backtick or a heredoc opens one and the same token closes it.
	 * A single-quoted string PHP gives as string content never ends, so it is refused where it starts.
	 * @param  ?array{int, string, int}  $string
	 * @return ?array{int, string, int}
	 */
	private static function trackString(?array $string, int $kind, string $text, int $start, string $code): ?array
	{
		if ($string === null && $kind === Token::EncapsedAndWhitespace && str_starts_with($text, "'")) {
			throw new ParseException('Unterminated string', sourceOffset: $start, code: $code);
		} elseif ($kind === ord('"') || $kind === ord('`')) {
			return $string === null ? [$kind, $text, $start] : null;
		} elseif ($kind === Token::StartHeredoc) {
			return [$kind, $text, $start];
		} elseif ($kind === Token::EndHeredoc) {
			return null;
		}

		return $string;
	}


	private static function describeString(int $kind, string $text): string
	{
		return match ($kind) {
			ord('`') => 'shell command',
			Token::StartHeredoc => str_contains($text, "'") ? 'nowdoc' : 'heredoc',
			default => 'string',
		};
	}


	/**
	 * Splits whitespace into runs without a newline and single line endings (`"\n"`, `"\r\n"` or `"\r"`).
	 * @return list<string>
	 */
	private static function splitWhitespace(string $text): array
	{
		return strpbrk($text, "\r\n") === false
			? [$text]
			: preg_split('~(\r\n|\r|\n)~', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
	}
}
