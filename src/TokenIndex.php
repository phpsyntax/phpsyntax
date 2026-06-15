<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;

use function count, strlen;


/**
 * Order and positions of the tokens of a file, built lazily and kept up to date after mutations rather than
 * rebuilt: the tokens of adopted and released children move at the next query, and the numbering, the lines,
 * the offsets, the columns and the visual columns after a change are brought up to date as far as the queries reach.
 * @internal the tokens and the nodes ask it for what they give their callers
 */
final class TokenIndex
{
	/** @var list<Token> */
	private array $tokens = [];

	/** @var array<int, int>  by index, valid up to `$lined` */
	private array $lines = [];

	/** @var array<int, int>  byte offset of the token text by index, valid up to `$positioned` */
	private array $offsets = [];

	/** @var array<int, int>  1-based, in UTF-8 characters, by index, valid up to `$positioned` */
	private array $columns = [];

	private bool $orderValid = false;

	/** number of leading tokens whose `Token::$index` is current */
	private int $numbered = 0;

	/** number of leading tokens whose line is current */
	private int $lined = 0;

	/** number of leading tokens whose offset and column are current */
	private int $positioned = 0;

	/** @var array<int, int>  visual column of the token by index, valid up to `$visualized`, for the tab width `$visualTabWidth` */
	private array $visualColumns = [];

	/** number of leading tokens whose visual column is current */
	private int $visualized = 0;

	private int $visualTabWidth = 0;

	/** the tree changed shape since the tokens were last moved */
	private bool $structureChanged = false;

	/** @var list<array{int, int}>  index ranges of the tokens released since the last update */
	private array $released = [];

	/** @var list<Node|Token>  children adopted since the last update */
	private array $adopted = [];


	public function __construct(
		private readonly Node $root,
	) {
	}


	/**
	 * The text or trivia of the token changed.
	 * @param int $lineEndings  how many line endings the token gained (or lost, when negative)
	 * @param bool $leading  the change is before the token, so its own line moves too
	 * @internal called by FileNode
	 */
	public function updateToken(Token $token, int $lineEndings, bool $leading): void
	{
		if (!$this->orderValid) {
			return;
		} elseif ($lineEndings !== 0) {
			$index = $this->getOrdinal($token);
			$this->lined = min($this->lined, $leading ? $index : $index + 1);
		} elseif ($this->structureChanged && !$this->hasCurrentOrdinal($token)) {
			// before the pending move, a token not numbered yet stands after all the numbered ones, or it is one
			// the move brings in, which invalidates the positions from where it puts it
			$this->invalidatePositions($this->numbered);
			return;
		} else {
			$index = $this->findOrdinal($token);
		}

		$this->invalidatePositions($index === null ? 0 : ($leading ? $index : $index + 1));
	}


	/**
	 * A child is about to be released from the tree: its tokens leave the order at the next query. The call reads
	 * the tree, so it comes before the adoption that takes the child's place, never after.
	 * @internal called by FileNode
	 */
	public function released(Node|Token $child): void
	{
		if (!$this->orderValid) {
			return;
		}

		$first = $child->getFirstToken();
		$last = $child->getLastToken();
		if ($first === null || $last === null) {
			return;
		}

		$this->released[] = [$this->getOrdinal($first), $this->getOrdinal($last)];
	}


	/**
	 * A child was adopted into the tree: its tokens join the order at the next query, where the tree puts them.
	 * @internal called by FileNode
	 */
	public function adopted(Node|Token $child): void
	{
		if ($this->orderValid) {
			$this->adopted[] = $child;
		}
	}


	/**
	 * The tree changes shape: the tokens of the released and adopted children are moved at the next query,
	 * when the tree is in its new shape whatever the order of the writes that got it there.
	 * @internal called by FileNode
	 */
	public function structureChanged(): void
	{
		$this->structureChanged = true;
	}


	/** Moves the tokens of the released and adopted children to where the tree has them. */
	private function applyStructure(): void
	{
		$this->structureChanged = false;
		$released = $this->released;
		$incoming = [];
		foreach ($this->adopted as $child) {
			if (self::hasAncestorAmong($child, $this->adopted)) {
				continue; // it came to stand inside another adopted child, whose tokens bring it in already
			}

			$tokens = [];
			if ($child instanceof Token) {
				$tokens[] = $child;
			} else {
				self::collect($child, $tokens);
			}

			if ($tokens !== []) {
				$incoming[] = [$child, $tokens];
			}
		}

		$this->released = $this->adopted = [];

		// a child taking the place of a released one with as many tokens is written over it, nothing else moves;
		// the token before the place must stay where it stands, not leave with a released range
		foreach ($incoming as $i => [$child, $tokens]) {
			$before = self::findTokenBefore($child);
			foreach ($released as $r => [$start, $end]) {
				if (
					$end - $start + 1 === count($tokens)
					&& ($this->tokens[$start - 1] ?? null) === $before
					&& !array_any($released, fn(array $range) => $range[0] < $start && $start - 1 <= $range[1])
				) {
					foreach ($tokens as $k => $token) {
						$this->tokens[$start + $k] = $token;
					}

					$this->touch($start);
					unset($incoming[$i], $released[$r]);
					break;
				}
			}
		}

		usort($released, fn(array $a, array $b) => $b[0] <=> $a[0]);
		foreach ($released as [$start, $end]) {
			array_splice($this->tokens, $start, $end - $start + 1);
			$this->touch($start);
		}

		foreach ($incoming as [$child, $tokens]) {
			$position = 0;
			for ($before = self::findTokenBefore($child); $before !== null; $before = self::findTokenBefore($before)) {
				$index = $this->findOrdinal($before); // null for a token of another adopted child, not placed yet
				if ($index !== null) {
					$position = $index + 1;
					break;
				}
			}

			array_splice($this->tokens, $position, 0, $tokens);
			$this->touch($position);
		}
	}


	/**
	 * Whether one of the others holds the child: a subtree adopted and then filled stands in the list twice,
	 * once as itself and once inside what it was put into, and its tokens belong to the order once.
	 * @param list<Node|Token> $others
	 */
	private static function hasAncestorAmong(Node|Token $child, array $others): bool
	{
		for ($ancestor = $child->parent; $ancestor !== null; $ancestor = $ancestor->parent) {
			if (in_array($ancestor, $others, true)) {
				return true;
			}
		}

		return false;
	}


	/** @return list<Token> */
	public function getTokens(): array
	{
		$this->ensureOrder();
		return $this->tokens;
	}


	public function getNext(Token $token): ?Token
	{
		return $this->tokens[$this->getOrdinal($token) + 1] ?? null;
	}


	public function getPrevious(Token $token): ?Token
	{
		$index = $this->getOrdinal($token);
		return $index > 0 ? $this->tokens[$index - 1] : null;
	}


	public function getOrdinal(Token $token): int
	{
		$this->ensureOrder();
		return $this->findOrdinal($token)
			?? throw new \InvalidArgumentException('The token does not belong to the indexed tree.');
	}


	/** Whether the token is in the tree the index describes. */
	public function contains(Token $token): bool
	{
		$this->ensureOrder();
		return $this->findOrdinal($token) !== null;
	}


	public function getOffset(Token $token): int
	{
		$index = $this->getOrdinal($token);
		$this->ensurePositions($index);
		return $this->offsets[$index];
	}


	public function getLine(Token $token): int
	{
		$index = $this->getOrdinal($token);
		$this->ensureLines($index);
		return $this->lines[$index];
	}


	public function getColumn(Token $token): int
	{
		$index = $this->getOrdinal($token);
		$this->ensurePositions($index);
		return $this->columns[$index];
	}


	/**
	 * Column with tabs expanded to the next multiple of the tab width, 1-based.
	 */
	public function getVisualColumn(Token $token, Style $style): int
	{
		$index = $this->getOrdinal($token);
		if ($this->visualTabWidth !== $style->tabWidth) {
			$this->visualTabWidth = $style->tabWidth;
			$this->visualized = 0;
		}

		if ($this->visualized <= $index) {
			$column = 0;
			if ($this->visualized > 0) {
				$previous = $this->tokens[$this->visualized - 1];
				$column = self::advanceVisually($this->visualColumns[$this->visualized - 1] - 1, $previous->text, $style);
				foreach ($previous->trailingTrivia as $trivia) {
					$column = self::advanceVisually($column, $trivia->text, $style);
				}
			}

			for ($i = $this->visualized; $i <= $index; $i++) {
				$current = $this->tokens[$i];
				foreach ($current->leadingTrivia as $trivia) {
					$column = self::advanceVisually($column, $trivia->text, $style);
				}

				$this->visualColumns[$i] = $column + 1;
				$column = self::advanceVisually($column, $current->text, $style);
				foreach ($current->trailingTrivia as $trivia) {
					$column = self::advanceVisually($column, $trivia->text, $style);
				}
			}

			$this->visualized = $index + 1;
		}

		return $this->visualColumns[$index];
	}


	/** The visual column, 0-based, after the text written from the column given; a line ending starts from zero. */
	private static function advanceVisually(int $column, string $text, Style $style): int
	{
		return preg_match('~[\r\n]~', $text)
			? Indentation::advance(0, (string) preg_replace('~^.*[\r\n]~s', '', $text), $style)
			: Indentation::advance($column, $text, $style);
	}


	/**
	 * Number of line endings (`"\n"`, `"\r\n"` or a lone `"\r"`) in the text.
	 * @internal shared by the classes of the library
	 */
	public static function countLineEndings(string $text): int
	{
		$lf = substr_count($text, "\n");
		$cr = substr_count($text, "\r");
		return $cr === 0 ? $lf : $lf + $cr - substr_count($text, "\r\n");
	}


	/**
	 * @param list<Trivia> $trivias
	 * @internal shared by the classes of the library
	 */
	public static function countLineEndingsIn(array $trivias): int
	{
		$count = 0;
		foreach ($trivias as $trivia) {
			$count += self::countLineEndings($trivia->text);
		}

		return $count;
	}


	/**
	 * Number of characters of the UTF-8 text, which is its bytes without the ones continuing a character.
	 * @internal shared by the classes of the library
	 */
	public static function countCharacters(string $text): int
	{
		return strlen($text) - preg_match_all('~[\x80-\xBF]~', $text);
	}


	/** Index of a token of the tree in its current order; null for a token that is not in it. */
	private function findOrdinal(Token $token): ?int
	{
		if ($this->hasCurrentOrdinal($token)) {
			return $token->ordinal;
		}

		$this->renumber($token);
		return $this->hasCurrentOrdinal($token) ? $token->ordinal : null;
	}


	/** Whether the index the token carries is where it stands in the order, which renumbering is not needed for. */
	private function hasCurrentOrdinal(Token $token): bool
	{
		return ($this->tokens[$token->ordinal] ?? null) === $token;
	}


	/** Numbers the tokens after the last numbered one, up to the token given or the end. */
	private function renumber(Token $upTo): void
	{
		for ($i = $this->numbered, $n = count($this->tokens); $i < $n; $i++) {
			$token = $this->tokens[$i];
			$token->ordinal = $i;
			$token->indexedBy = $this;
			if ($token === $upTo) {
				$i++;
				break;
			}
		}

		$this->numbered = $i;
	}


	/** The order changed from the position on: numbering, lines and positions there are stale. */
	private function touch(int $position): void
	{
		$this->numbered = min($this->numbered, $position);
		$this->lined = min($this->lined, $position);
		$this->invalidatePositions($position);
	}


	/** The offsets, columns and visual columns from the index on are stale. */
	private function invalidatePositions(int $from): void
	{
		$this->positioned = min($this->positioned, $from);
		$this->visualized = min($this->visualized, $from);
	}


	private function ensureOrder(): void
	{
		if ($this->orderValid) {
			if ($this->structureChanged) {
				$this->applyStructure();
			}

			return;
		}

		$this->tokens = [];
		self::collect($this->root, $this->tokens);
		$this->numbered = $this->lined = 0;
		$this->invalidatePositions(0);
		$this->orderValid = true;
		$this->structureChanged = false;
		$this->released = $this->adopted = [];
	}


	/** @param list<Token> $tokens */
	private static function collect(Node $node, array &$tokens): void
	{
		foreach ($node->getChildren() as $child) {
			if ($child->parent !== $node) { // a write took it and left the hole behind: it stands twice
				throw new \LogicException(
					($child instanceof Token ? 'Token ' . Helpers::formatCode($child->text) : '`' . $child::class . '`')
					. ' stands in the file twice: a write took it out of a subtree that entered the file afterwards; a clone keeps it in both places.',
				);
			}

			if ($child instanceof Token) {
				$tokens[] = $child;
			} else {
				self::collect($child, $tokens);
			}
		}
	}


	/** The nearest token before the node in the tree, whatever the state of the index. */
	private static function findTokenBefore(Node|Token $node): ?Token
	{
		for ($child = $node; ($parent = $child->parent) !== null; $child = $parent) {
			$siblings = $parent->getChildren();
			$index = array_search($child, $siblings, strict: true);
			if ($index === false) {
				throw new \LogicException(
					($child instanceof Token ? 'Token ' . Helpers::formatCode($child->text) : '`' . $child::class . '`')
					. ' is not among the children of its parent `' . $parent::class . '`: the tree was changed without its setters.',
				);
			}

			for ($i = $index - 1; $i >= 0; $i--) {
				$token = $siblings[$i]->getLastToken();
				if ($token !== null) {
					return $token;
				}
			}
		}

		return null;
	}


	/** Counts the lines after the last counted one, up to the index given. */
	private function ensureLines(int $upTo): void
	{
		if ($this->lined > $upTo) {
			return;
		}

		$line = 1;
		if ($this->lined > 0) {
			$previous = $this->tokens[$this->lined - 1];
			$line = $this->lines[$this->lined - 1]
				+ self::countLineEndings($previous->text)
				+ self::countLineEndingsIn($previous->trailingTrivia);
		}

		for ($i = $this->lined; $i <= $upTo; $i++) {
			$token = $this->tokens[$i];
			$line += self::countLineEndingsIn($token->leadingTrivia);
			$this->lines[$i] = $line;
			$line += self::countLineEndings($token->text) + self::countLineEndingsIn($token->trailingTrivia);
		}

		$this->lined = $upTo + 1;
	}


	/** Computes the offsets and columns after the last current one, up to the index given. */
	private function ensurePositions(int $upTo): void
	{
		if ($this->positioned > $upTo) {
			return;
		}

		$offset = 0;
		$column = 1;
		if ($this->positioned > 0) {
			$previous = $this->tokens[$this->positioned - 1];
			$offset = $this->offsets[$this->positioned - 1];
			$column = $this->columns[$this->positioned - 1];
			self::advance($previous->text, $offset, $column);
			foreach ($previous->trailingTrivia as $trivia) {
				self::advance($trivia->text, $offset, $column);
			}
		}

		for ($i = $this->positioned; $i <= $upTo; $i++) {
			$token = $this->tokens[$i];
			foreach ($token->leadingTrivia as $trivia) {
				self::advance($trivia->text, $offset, $column);
			}

			$this->offsets[$i] = $offset;
			$this->columns[$i] = $column;
			self::advance($token->text, $offset, $column);
			foreach ($token->trailingTrivia as $trivia) {
				self::advance($trivia->text, $offset, $column);
			}
		}

		$this->positioned = $upTo + 1;
	}


	private static function advance(string $text, int &$offset, int &$column): void
	{
		$offset += strlen($text);
		$newlines = preg_match_all('~\r\n|\r|\n~', $text, $m, PREG_OFFSET_CAPTURE);
		if ($newlines) {
			$last = $m[0][$newlines - 1];
			$column = 1 + self::countCharacters(substr($text, $last[1] + strlen($last[0])));
		} else {
			$column += self::countCharacters($text);
		}
	}
}
