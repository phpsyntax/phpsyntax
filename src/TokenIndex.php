<?php declare(strict_types=1);

namespace PhpSyntax;

use function count, strlen;


/**
 * Order and positions of the tokens of a file, built lazily and kept up to date after mutations: a structural
 * change moves the tokens of the children it adopted and released (a change of unknown extent rebuilds the
 * order), a change of the text or trivia of a token leaves the lines before it alone, and only the offsets and
 * columns are recomputed from scratch. The tokens of adopted and released children are moved at the first query
 * after the change, and the numbering and the lines of the tokens after a change are brought up to date lazily,
 * as far as the queries reach. Moving tokens shifts the ones after them in the order, so a structural change
 * costs in proportion to the file, a change of text or trivia in proportion to how far the queries reach.
 */
final class TokenIndex
{
	/** @var list<Token> */
	private array $tokens = [];

	/** @var array<int, int>  by index, valid up to $lined */
	private array $lines = [];

	/** @var list<int>  byte offset of the token text */
	private array $offsets = [];

	/** @var list<int>  1-based, in UTF-8 characters */
	private array $columns = [];

	private bool $orderValid = false;

	/** number of leading tokens whose Token::$index is current */
	private int $numbered = 0;

	/** number of leading tokens whose line is current */
	private int $lined = 0;

	private bool $positionsValid = false;

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
	 * @internal
	 */
	public function updateToken(Token $token, int $lineEndings, bool $leading): void
	{
		$this->positionsValid = false;
		if ($lineEndings === 0 || !$this->orderValid) {
			return;
		}

		$index = $this->getIndex($token);
		$this->lined = min($this->lined, $leading ? $index : $index + 1);
	}


	/**
	 * A child is about to be released from the tree: its tokens leave the order at the next query. The call reads
	 * the tree, so it comes before the adoption that takes the child's place, never after.
	 * @internal
	 */
	public function released(Node|Token $child): void
	{
		if (!$this->orderValid) {
			return;
		}

		$first = $child instanceof Token ? $child : $child->getFirstToken();
		$last = $child instanceof Token ? $child : $child->getLastToken();
		if ($first === null || $last === null) {
			return;
		}

		$this->released[] = [$this->getIndex($first), $this->getIndex($last)];
	}


	/**
	 * A child was adopted into the tree: its tokens join the order at the next query, where the tree puts them.
	 * @internal
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
	 * @internal
	 */
	public function structureChanged(): void
	{
		$this->positionsValid = false;
		$this->structureChanged = true;
	}


	/** Moves the tokens of the released and adopted children to where the tree has them now. */
	private function applyStructure(): void
	{
		$this->structureChanged = false;
		$released = $this->released;
		$adopted = $this->adopted;
		$this->released = $this->adopted = [];
		usort($released, fn(array $a, array $b) => $b[0] <=> $a[0]);
		foreach ($released as [$start, $end]) {
			array_splice($this->tokens, $start, $end - $start + 1);
			$this->touch($start);
		}

		foreach ($adopted as $child) {
			if (self::hasAncestorAmong($child, $adopted)) {
				continue; // it came to stand inside another adopted child, whose tokens bring it in already
			}

			$tokens = [];
			if ($child instanceof Token) {
				$tokens[] = $child;
			} else {
				self::collect($child, $tokens);
			}

			if ($tokens === []) {
				continue;
			}

			$position = 0;
			for ($before = self::findTokenBefore($child); $before !== null; $before = self::findTokenBefore($before)) {
				$index = $this->indexOf($before); // null for a token of another adopted child, not placed yet
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
			foreach ($others as $other) {
				if ($other === $ancestor) {
					return true;
				}
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
		return $this->tokens[$this->getIndex($token) + 1] ?? null;
	}


	public function getPrevious(Token $token): ?Token
	{
		$index = $this->getIndex($token);
		return $index > 0 ? $this->tokens[$index - 1] : null;
	}


	public function getIndex(Token $token): int
	{
		$this->ensureOrder();
		return $this->indexOf($token)
			?? throw new \InvalidArgumentException('The token does not belong to the indexed tree.');
	}


	/** Whether the token is in the tree the index describes. */
	public function contains(Token $token): bool
	{
		$this->ensureOrder();
		return $this->indexOf($token) !== null;
	}


	public function getOffset(Token $token): int
	{
		$this->ensurePositions();
		return $this->offsets[$this->getIndex($token)];
	}


	public function getLine(Token $token): int
	{
		$index = $this->getIndex($token);
		$this->ensureLines($index);
		return $this->lines[$index];
	}


	public function getColumn(Token $token): int
	{
		$this->ensurePositions();
		return $this->columns[$this->getIndex($token)];
	}


	/**
	 * Column with tabs expanded to the next multiple of the tab width, 1-based.
	 */
	public function getVisualColumn(Token $token, Style $style): int
	{
		$prefix = '';
		for ($i = $this->getIndex($token) - 1; $i >= 0; $i--) {
			$prefix = $this->tokens[$i] . $prefix;
			if (preg_match('~[\r\n]~', $prefix)) {
				break;
			}
		}

		foreach ($token->leadingTrivia as $trivia) {
			$prefix .= $trivia->text;
		}

		return Indentation::advance(0, (string) preg_replace('~^.*[\r\n]~s', '', $prefix), $style) + 1;
	}


	/** Number of line endings ("\n", "\r\n" or a lone "\r") in the text. */
	public static function countLineEndings(string $text): int
	{
		$lf = substr_count($text, "\n");
		$cr = substr_count($text, "\r");
		return $cr === 0 ? $lf : $lf + $cr - substr_count($text, "\r\n");
	}


	/** @param list<Trivia> $trivias */
	public static function countLineEndingsIn(array $trivias): int
	{
		$count = 0;
		foreach ($trivias as $trivia) {
			$count += self::countLineEndings($trivia->text);
		}

		return $count;
	}


	/** Number of characters of the UTF-8 text, which is its bytes without the ones continuing a character. */
	public static function countCharacters(string $text): int
	{
		return strlen($text) - preg_match_all('~[\x80-\xBF]~', $text);
	}


	/** Index of a token of the tree in its current order; null for a token that is not in it. */
	private function indexOf(Token $token): ?int
	{
		$index = $token->index;
		if (($this->tokens[$index] ?? null) === $token) {
			return $index;
		}

		$this->renumber($token);
		$index = $token->index;
		return ($this->tokens[$index] ?? null) === $token ? $index : null;
	}


	/** Numbers the tokens after the last numbered one, up to the token given or the end. */
	private function renumber(?Token $upTo): void
	{
		for ($i = $this->numbered, $n = count($this->tokens); $i < $n; $i++) {
			$token = $this->tokens[$i];
			$token->index = $i;
			$token->indexedBy = $this;
			if ($token === $upTo) {
				$i++;
				break;
			}
		}

		$this->numbered = $i;
	}


	/** The order changed from the position on: numbering and lines there are stale. */
	private function touch(int $position): void
	{
		$this->numbered = min($this->numbered, $position);
		$this->lined = min($this->lined, $position);
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
		$this->orderValid = true;
		$this->positionsValid = false;
		$this->structureChanged = false;
		$this->released = $this->adopted = [];
	}


	/** @param list<Token> $tokens */
	private static function collect(Node $node, array &$tokens): void
	{
		foreach ($node->getChildren() as $child) {
			if ($child->parent !== $node) { // a write took it and left the hole behind: it stands twice
				throw new \LogicException('The node was taken out of the subtree, clone it first.');
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
				throw new \LogicException('The node is not a child of its parent.');
			}

			for ($i = $index - 1; $i >= 0; $i--) {
				$token = $siblings[$i] instanceof Token ? $siblings[$i] : $siblings[$i]->getLastToken();
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


	private function ensurePositions(): void
	{
		$this->ensureOrder();
		if ($this->positionsValid) {
			return;
		}

		$this->offsets = $this->columns = [];
		$offset = 0;
		$column = 1;
		foreach ($this->tokens as $token) {
			foreach ($token->leadingTrivia as $trivia) {
				self::advance($trivia->text, $offset, $column);
			}

			$this->offsets[] = $offset;
			$this->columns[] = $column;
			self::advance($token->text, $offset, $column);
			foreach ($token->trailingTrivia as $trivia) {
				self::advance($trivia->text, $offset, $column);
			}
		}

		$this->positionsValid = true;
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
