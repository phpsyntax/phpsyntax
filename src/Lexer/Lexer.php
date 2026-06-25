<?php declare(strict_types=1);

namespace PhpSyntax\Lexer;

use PhpSyntax\ParseException;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;
use PhpSyntax\Trivia;
use PhpSyntax\TriviaKind;
use function count, defined, ord, sprintf, strlen;


/**
 * Turns source code into significant tokens; whitespace, comments and open tags become trivia.
 * Trailing trivia of a token reaches up to and including the end of its line, everything else
 * is leading trivia of the next token; an open tag always starts leading trivia, and the final
 * EndOfFile token carries only leading trivia.
 */
final class Lexer
{
	/** @var array<int, int>  host token id → TokenKind */
	private array $kinds = [];

	/** @var list<Emulator> */
	private array $emulators;


	/** @param ?list<Emulator> $emulators  null = those needed by the running PHP version */
	public function __construct(?array $emulators = null)
	{
		$this->emulators = $emulators ?? self::createHostEmulators();
		foreach (TokenKind::HostConstants as $name => $kind) {
			if (defined($name)) {
				$this->kinds[constant($name)] = $kind;
			}
		}
	}


	/**
	 * Emulators of syntax the running PHP version does not tokenize itself.
	 * @return list<Emulator>
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
		$raw = $this->tokenizeRaw($code, $withPositions);
		foreach ($this->emulators as $emulator) {
			if ($emulator->isNeeded($code)) {
				$raw = $emulator->emulate($raw);
			}
		}

		return $this->foldTrivia($raw, $code);
	}


	/**
	 * Tokens with host ids replaced by TokenKind; whitespace and comments are still tokens.
	 * @return list<Token>
	 */
	private function tokenizeRaw(string $code, bool $withPositions): array
	{
		$tokens = [];
		foreach (@\PhpToken::tokenize($code) as $token) { // @ - compile warnings for invalid escape sequences in strings
			$kind = $this->kinds[$token->id] ?? null;
			if ($kind === null) {
				if ($token->id === T_BAD_CHARACTER) {
					throw new ParseException(
						sprintf('Unexpected character "%s" (ASCII %d)', $token->text, ord($token->text)),
						$token->line,
						$token->pos,
						$code,
					);
				}

				$kind = $token->id; // single character
			}

			$tokens[] = $withPositions
				? new Token($kind, $token->text, $token->pos, $token->line)
				: new Token($kind, $token->text);
		}

		return $tokens;
	}


	/**
	 * @param  list<Token>  $raw
	 * @return list<Token>
	 */
	private function foldTrivia(array $raw, string $code): array
	{
		$tokens = [];
		$leading = []; // trivia for the next token
		$open = null; // token whose line is still open for trailing trivia
		$braces = []; // brace nesting inside string interpolation
		$halt = 0; // 1 = after __halt_compiler, 2 = its data follow
		$line = 1;

		foreach ($raw as $token) {
			$kind = $token->kind;
			$line = $token->originalLine ?? $line;
			$inInterpolation = $braces !== [];

			if ($kind === TokenKind::Whitespace) {
				$pieceLine = $token->originalLine;
				foreach (self::splitWhitespace($token->text) as $piece) {
					$isEol = $piece[0] === "\n" || $piece[0] === "\r";
					$trivia = new Trivia($isEol ? TriviaKind::EndOfLine : TriviaKind::Whitespace, $piece, $inInterpolation, $pieceLine);
					$pieceLine = $isEol && $pieceLine !== null ? $pieceLine + 1 : $pieceLine;
					if ($open) {
						$open->setTrailingTrivia([...$open->trailingTrivia, $trivia]);
						if ($isEol) {
							$open = null;
						}
					} else {
						$leading[] = $trivia;
					}
				}
				continue;

			} elseif ($kind === TokenKind::Comment || $kind === TokenKind::DocComment) {
				if (str_starts_with($token->text, '/*') && !str_ends_with($token->text, '*/')) {
					throw new ParseException('Unterminated comment', $token->originalLine, $token->originalOffset, $code);
				}

				$trivia = new Trivia($kind === TokenKind::Comment ? TriviaKind::Comment : TriviaKind::DocComment, $token->text, $inInterpolation, $token->originalLine);
				if ($open) {
					$open->setTrailingTrivia([...$open->trailingTrivia, $trivia]);
				} else {
					$leading[] = $trivia;
				}
				continue;

			} elseif ($kind === TokenKind::OpenTag) {
				$leading[] = new Trivia(TriviaKind::OpenTag, $token->text, originalLine: $token->originalLine);
				$open = null;
				continue;

			} elseif ($kind === TokenKind::HaltCompiler) {
				$halt = 1;

			} elseif (($kind === ord(';') || $kind === TokenKind::CloseTag) && $halt === 1) {
				$halt = 2;

			} elseif ($kind === TokenKind::InlineHtml && $halt === 2) {
				$token->kind = TokenKind::HaltCompilerData;

			} elseif ($kind === TokenKind::CurlyOpen || $kind === TokenKind::DollarOpenCurlyBraces) {
				$braces[] = true;

			} elseif ($kind === ord('{') && $inInterpolation) {
				$braces[] = false;

			} elseif ($kind === ord('}') && $inInterpolation) {
				array_pop($braces);
			}

			$token->setLeadingTrivia($leading);
			$leading = [];
			$tokens[] = $token;
			$end = $token->text[-1] ?? '';
			$open = $end === "\n" || $end === "\r" ? null : $token;
		}

		if ($raw) {
			$line += preg_match_all('~\r\n|\r|\n~', $raw[count($raw) - 1]->text);
		}

		$eof = new Token(TokenKind::EndOfFile, '', strlen($code), $line);
		$eof->setLeadingTrivia($leading);
		$tokens[] = $eof;
		return $tokens;
	}


	/**
	 * Splits whitespace into runs without a newline and single line endings ("\n", "\r\n" or "\r").
	 * @return list<string>
	 */
	private static function splitWhitespace(string $text): array
	{
		return strpbrk($text, "\r\n") === false
			? [$text]
			: preg_split('~(\r\n|\r|\n)~', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
	}
}
