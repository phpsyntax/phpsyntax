<?php declare(strict_types=1);

namespace PhpSyntax;

use PhpSyntax\Nodes\Expression\BinaryOpNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\Scalar\HeredocNode;
use PhpSyntax\Nodes\Scalar\InterpolatedStringPartNode;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\Statement\BlockNode;
use PhpSyntax\Nodes\Statement\NamespaceNode;
use PhpSyntax\Nodes\StatementNode;
use function count, strlen;


/**
 * The indentation of a line: what it is, what its place in the tree conventionally gives it, and how it is
 * set together with the comments above it.
 */
final class Indentation
{
	/**
	 * Whether the line of the token and the comments on their own lines above it already carry the indentation,
	 * so that set() with the same arguments would change nothing.
	 */
	public static function has(Token $token, string $indentation, string $commentIndentation): bool
	{
		if ($token->getIndentation() !== $indentation) {
			return false;
		}

		foreach ($token->leadingTrivia as $trivia) {
			if ($trivia->isComment()) {
				return self::reindentComments($token->leadingTrivia, $commentIndentation) === null;
			}
		}

		return true;
	}


	/**
	 * Whether the line ending above the token is trivia, so that the indentation of its line is whitespace
	 * a rule may change. Token::startsLine() also answers yes where the previous token ends with a line
	 * ending inside its own text, and there the leading whitespace is that text: the body of a heredoc,
	 * inline HTML, the data after __halt_compiler().
	 */
	public static function opensLine(Token $token): bool
	{
		$previous = $token->getPrevious();
		$before = $previous === null ? [] : $previous->trailingTrivia;
		$last = $before[count($before) - 1] ?? null;
		if ($last !== null && $last->isEndOfLine() && !$last->inInterpolation) {
			return true;
		}

		foreach ($token->leadingTrivia as $trivia) {
			if ($trivia->isEndOfLine() && !$trivia->inInterpolation) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Whether the token begins the statement it stands in, so that the line it opens is the line of that
	 * statement.
	 */
	public static function opensStatement(Token $token): bool
	{
		for ($node = $token->parent; $node !== null; $node = $node->parent) {
			if ($node instanceof StatementNode) {
				return $node->getFirstToken() === $token;
			}
		}

		return false;
	}


	/**
	 * The trivia a report about the indentation of the token belongs on: the whitespace that stands for it,
	 * or the first trivia when the line has none, so that the report lands on the line the reader sees and
	 * a suppression comment written there matches it.
	 */
	public static function findTrivia(Token $token): ?Trivia
	{
		$leading = $token->leadingTrivia;
		$last = $leading[count($leading) - 1] ?? null;
		return $last?->kind === TriviaKind::Whitespace ? $last : ($leading[0] ?? null);
	}


	/**
	 * The node whose line the token continues, the lowest ancestor the token does not begin, and the child
	 * of it the token stands in: a slot value, or a list for an item and for a separator alike.
	 * @return array{?Node, Node|Token}
	 */
	public static function findOwner(Token $token): array
	{
		$child = $token;
		$node = $token->parent;
		while ($node !== null && self::begins($node, $child)) {
			$child = $node;
			$node = $node->parent;
		}

		while ($node instanceof NodeList || $node instanceof SeparatedNodeList) {
			$child = $node;
			$node = $node->parent;
		}

		return [$node, $child];
	}


	/** Whether the child is the first of the children of the node that has a token. */
	private static function begins(Node $node, Node|Token $child): bool
	{
		foreach ($node->getChildren() as $sibling) {
			if ($sibling === $child) {
				return true;
			} elseif ($sibling instanceof Token || $sibling->getFirstToken() !== null) {
				return false;
			}
		}

		return false;
	}


	/**
	 * The layout role of the slot the child fills, and the item of it the token begins: the child itself,
	 * or in a list the item holding the token, or the first item for a separator.
	 * @return array{LayoutRole, Node|Token}
	 */
	public static function findRole(Node $owner, Node|Token $child, Token $token): array
	{
		$slot = $owner->findSlotOf($child);
		$role = $slot === null ? LayoutRole::Content : LayoutData::Roles[$owner::class][$slot] ?? LayoutRole::Content;
		if ($role === LayoutRole::Operator && $owner instanceof BinaryOpNode && $owner->operator->is(TokenKind::Pipe)) {
			$role = LayoutRole::Link; // a pipeline is a chain of calls, not a computation
		}

		$item = $child;
		if ($child instanceof NodeList || $child instanceof SeparatedNodeList) {
			$item = $token;
			while ($item->parent !== null && $item->parent !== $child) {
				$item = $item->parent;
			}

			if ($item instanceof Token) {
				$item = $child->getChildren()[0] ?? $item;
			}
		}

		return [$role, $item];
	}


	/**
	 * The indentation a line opened by the token conventionally has, counted from the line the construct it
	 * continues begins on: one unit deeper for its content, level with it for what closes or continues it.
	 * A rule that puts a token on a line of its own gives the line this, and a rule about indentation may
	 * then place it by its own options.
	 */
	public static function infer(Token $token, Style $style): string
	{
		[$owner, $child] = self::findOwner($token);
		if ($owner === null) {
			return '';
		}

		[$role] = self::findRole($owner, $child, $token);
		// the content of a body counts from the line its structure begins on, wherever the brace stands
		$structure = $owner instanceof BlockNode && !$owner->parent instanceof NodeList ? $owner->parent : $owner;
		$first = $structure?->getFirstToken() ?? $token;
		$level = match ($role) {
			LayoutRole::Content => $owner instanceof NamespaceNode && $owner->openBrace === null ? 0 : 1,
			LayoutRole::Anchor, LayoutRole::Closes => 0,
			LayoutRole::Body => $child instanceof BlockNode ? 0 : 1,
			// an operator lines up with the line its expression begins on, unless that line is shared with
			// what holds it or is the line of the statement, where lining up would say nothing
			LayoutRole::Operator => $first->startsLine() && !self::opensStatement($first) ? 0 : 1,
			default => 1,
		};
		return self::normalize($first->getLineIndentation(), $style) . $style->indent($level);
	}


	/**
	 * Moves every line the node opens by the levels of the style, and with them the body and the closing
	 * delimiter of a heredoc, which keeps its value where PHP strips the indentation of that delimiter.
	 * Inline HTML and the content of a string are text and stay as they are.
	 */
	public static function shift(Node $node, int $levels, Style $style): void
	{
		if ($levels === 0) {
			return;
		}

		foreach ($node->getTokens() as $token) {
			if (self::opensLine($token)) {
				$indentation = self::move(self::normalize($token->getIndentation(), $style), $levels, $style);
				self::set($token, $indentation, $indentation);
			}
		}

		foreach ($node->find(HeredocNode::class) as $heredoc) {
			self::shiftHeredoc($heredoc, $levels, $style);
		}
	}


	/** The indentation moved by the levels, taken away from the front where they are negative. */
	private static function move(string $indentation, int $levels, Style $style): string
	{
		if ($levels > 0) {
			return $indentation . $style->indent($levels);
		}

		$unit = $style->indent;
		for ($i = 0; $i < -$levels && str_starts_with($indentation, $unit); $i++) {
			$indentation = substr($indentation, strlen($unit));
		}

		return $indentation;
	}


	/**
	 * Moves the lines of a heredoc: what its body lines and its closing delimiter share is what PHP takes
	 * off every line, so moving them together leaves the value as it was.
	 */
	private static function shiftHeredoc(HeredocNode $heredoc, int $levels, Style $style): void
	{
		$parts = $heredoc->parts->getItems();
		$old = $heredoc->indentation;
		$new = self::move(self::normalize($old, $style), $levels, $style);
		if ($new === $old || !($parts[0] ?? null) instanceof InterpolatedStringPartNode) {
			return; // nothing to take off, or a line starts with an interpolation and cannot be moved
		}

		foreach ($parts as $i => $part) {
			if ($part instanceof InterpolatedStringPartNode) {
				$text = (string) preg_replace('~(\R)' . preg_quote($old, '~') . '~', '$1' . $new, $part->token->text);
				$part->token->setText($i === 0 ? $new . substr($text, strlen($old)) : $text);
			}
		}

		$heredoc->closeDelimiter->setText($new . ltrim($heredoc->closeDelimiter->text));
	}


	/**
	 * Sets the indentation of the line of the token (which must start a line) and of the comments above it.
	 */
	public static function set(Token $token, string $indentation, string $commentIndentation): void
	{
		$leading = self::reindentComments($token->leadingTrivia, $commentIndentation);
		if ($leading !== null) {
			$token->setLeadingTrivia($leading);
		}

		$token->setIndentation($indentation);
	}


	/**
	 * Visual width of an indentation, counted the way a tab is written: to the next stop of the style.
	 */
	public static function width(string $indentation, Style $style): int
	{
		return self::advance(0, $indentation, $style);
	}


	/**
	 * The column the text ends on, counted from the one it starts at: a tab moves to the next stop of the
	 * style wherever it stands, the rest counts in characters.
	 */
	public static function advance(int $column, string $text, Style $style): int
	{
		foreach (preg_split('~(\t)~', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $piece) {
			$column = $piece === "\t"
				? intdiv($column, $style->tabWidth) * $style->tabWidth + $style->tabWidth
				: $column + TokenIndex::countCharacters($piece); // characters, not bytes
		}

		return $column;
	}


	/**
	 * Indentation in the characters of the style: runs of spaces of the tab width become tabs, or tabs
	 * become the indentation unit.
	 */
	public static function normalize(string $indentation, Style $style): string
	{
		return $style->indent === "\t"
			? str_replace(str_repeat(' ', $style->tabWidth), "\t", $indentation)
			: str_replace("\t", $style->indent, $indentation);
	}


	/**
	 * Comments standing on their own lines among the leading trivia of a token get the indentation; null when
	 * nothing changes. A comment that stands on the line of the token belongs to that line, whose whitespace
	 * is the indentation Token::setIndentation() writes, and is left alone.
	 * @param  list<Trivia>  $trivia
	 * @return ?list<Trivia>
	 */
	public static function reindentComments(array $trivia, string $indentation): ?array
	{
		$result = [];
		$changed = false;
		$count = count($trivia);
		$lineStart = 0; // what follows stands on the line of the token
		foreach ($trivia as $i => $item) {
			if ($item->isEndOfLine() || $item->kind === TriviaKind::OpenTag) {
				$lineStart = $i + 1;
			}
		}

		for ($i = 0; $i < $count; $i++) {
			$item = $trivia[$i];
			$next = $trivia[$i + 1] ?? null;
			$atLineStart = $i < $lineStart
				&& ($i === 0 ? $item->kind !== TriviaKind::OpenTag : $trivia[$i - 1]->isEndOfLine());
			if ($atLineStart && $item->isComment()) {
				$comment = self::reindentComment($item, '', $indentation);
				$changed = $changed || $comment !== $item || $indentation !== '';
				if ($indentation !== '') {
					$result[] = new Trivia(TriviaKind::Whitespace, $indentation);
				}

				$result[] = $comment;
			} elseif ($atLineStart && $item->kind === TriviaKind::Whitespace && $next?->isComment()) {
				$comment = self::reindentComment($next, $item->text, $indentation);
				$changed = $changed || $item->text !== $indentation || $comment !== $next;
				if ($indentation !== '') {
					$result[] = new Trivia(TriviaKind::Whitespace, $indentation);
				}

				$result[] = $comment;
				$i++;
			} else {
				$result[] = $item;
			}
		}

		return $changed ? $result : null;
	}


	/**
	 * A multi-line comment whose every later line starts with a star takes the shape of a doc comment, the
	 * stars one space in from the opening; any other moves as a whole, its later lines losing the old
	 * indentation and getting the new one.
	 */
	public static function reindentComment(Trivia $comment, string $old, string $new): Trivia
	{
		if (!str_contains($comment->text, "\n")) {
			return $comment;
		}

		$text = preg_match('~^[^\r\n]*+(?:\R[ \t]*+\*[^\r\n]*+)*+$~D', $comment->text) === 1
			? preg_replace('~(\R)[ \t]*+(?=\*)~', '$1' . $new . ' ', $comment->text)
			: ($old === $new ? $comment->text : preg_replace('~(\R)' . preg_quote($old, '~') . '~', '$1' . $new, $comment->text));
		return $text === null || $text === $comment->text ? $comment : new Trivia($comment->kind, $text, $comment->inInterpolation);
	}
}
