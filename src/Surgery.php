<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;

use PhpSyntax\Nodes\{NodeList, SeparatedNodeList};
use function count;


/**
 * Takes a node out of the tree or puts another in its place, and sews up the trivia around the cut: the comments,
 * the lines and the whitespace the node leaves behind or the new one is given.
 * @internal the algorithm of `Node::remove()`, `Node::replaceWith()` and `Token::replaceWith()`
 */
final class Surgery
{
	/** See `Node::replaceWith()` and `Token::replaceWith()`. */
	public static function replace(Node|Token $old, Node|Token $new): void
	{
		$parent = $old->parent ?? throw new \LogicException(($old instanceof Token ? 'A token' : 'A node') . ' without a parent cannot be replaced.');
		if ($new === $old) {
			return;
		}

		$first = $old->getFirstToken();
		$last = $old->getLastToken();
		if ($first && $last && !$new->getFirstToken()) {
			$edges = [...$first->leadingTrivia, ...$last->trailingTrivia];
			$previous = self::findNeighbor($first, -1);
			$next = self::findNeighbor($last, 1);
			if ($edges && !$previous && !$next) {
				throw new \LogicException('The trivia around the node have no token to stay with once a node without tokens takes its place.');
			}

			$parent->replaceChild($old, $new);
			$first->setLeadingTrivia([]);
			$last->setTrailingTrivia([]);
			self::closeGap($previous, $edges, $next);
			return;
		}

		$parent->replaceChild($old, $new); // the parent takes the node before the trivia move, so a refused one leaves them where they stand
		if ($first) {
			$leading = $first->leadingTrivia;
			$first->setLeadingTrivia([]);
			if ($target = $new->getFirstToken()) {
				$target->setLeadingTrivia([...$leading, ...$target->leadingTrivia]);
			}
		}

		if ($last) {
			$trailing = $last->trailingTrivia;
			$last->setTrailingTrivia([]);
			if ($target = $new->getLastToken()) {
				$target->setTrailingTrivia([...$target->trailingTrivia, ...$trailing]);
			}
		}

		if (($first = $new->getFirstToken()) && ($last = $new->getLastToken())) {
			self::keepApart(self::findNeighbor($first, -1), $first);
			self::keepApart($last, self::findNeighbor($last, 1));
		}
	}


	/**
	 * Gives the trivia of a node that left no token behind to the tokens around the gap, where the lexer would have
	 * put them: the previous token takes them up to its line ending, the next one the rest, an open tag included.
	 * @param  list<Trivia>  $trivia
	 */
	private static function closeGap(?Token $previous, array $trivia, ?Token $next): void
	{
		if ($next) {
			$next->setLeadingTrivia([...$trivia, ...$next->leadingTrivia]);
			self::splitSeam($previous, $next);
		} else {
			$previous?->setTrailingTrivia([...$previous->trailingTrivia, ...$trivia]);
		}

		self::keepApart($previous, $next);
	}


	/**
	 * Whether what follows the token up to the next open tag is output of the script, which a close tag and inline HTML
	 * open, so that whitespace written there would change what the script prints.
	 */
	private static function opensOutput(Token $token): bool
	{
		return $token->is([Token::CloseTag, Token::InlineHtml]);
	}


	/** Puts a space between two tokens standing right against each other that the lexer would not read as the two. */
	private static function keepApart(?Token $left, ?Token $right): void
	{
		static $lexer = new Lexer;
		if (
			$left === null
			|| $right === null
			|| $right->text === ''
			|| $left->trailingTrivia
			|| $right->leadingTrivia
			|| $left->is([Token::EncapsedAndWhitespace, Token::InlineHtml])
			|| $right->is([Token::EncapsedAndWhitespace, Token::InlineHtml])
		) {
			return;
		}

		if (!$lexer->canAdjoin($left->text, $right->text)) {
			$left->setTrailingTrivia([Trivia::fromText(' ')]);
		}
	}


	/** The token before or after the given one, in a subtree without a file as well, where no index answers. */
	private static function findNeighbor(Token $token, int $step): ?Token
	{
		if ($token->getFile() !== null) {
			return $step < 0 ? $token->getPrevious() : $token->getNext();
		}

		for ($root = $token->parent; $root?->parent !== null; $root = $root->parent);
		$tokens = $root?->getTokens() ?? [];
		$index = array_search($token, $tokens, true);
		return $index === false ? null : $tokens[$index + $step] ?? null;
	}


	/** See `Node::remove()`. */
	public static function remove(Node $node, CommentPolicy $comments): void
	{
		$parent = $node->parent;
		if (!$parent instanceof NodeList) {
			throw new \LogicException('Only an item of a list can be removed; a slot is emptied by its setter.');
		}

		$tokens = $node->getTokens();
		$separatorAfter = false;
		if ($parent instanceof SeparatedNodeList && ($separator = $parent->findSeparatorOf($node)) !== null) {
			$separatorAfter = $separator !== ($tokens[0] ?? null)?->getPrevious(); // the last item has it before
			$tokens = $separatorAfter ? [...$tokens, $separator] : [$separator, ...$tokens];
		}

		$first = $tokens[0] ?? null;
		$last = $tokens[count($tokens) - 1] ?? null;
		$previous = $first?->getPrevious();
		$next = $last?->getNext();
		$alone = self::standsAlone($first->leadingTrivia ?? [], $last->trailingTrivia ?? [], $previous, $next);
		$own = $first->leadingTrivia ?? [];
		$lineStart = 0; // where the line of the node starts in its leading trivia, the head being the lines above it
		foreach ($alone ? $own : [] as $i => $trivia) {
			if ($trivia->isLineEnding()) {
				$lineStart = $i + 1;
			}
		}

		[$leading, $moved] = self::splitComments($alone ? array_slice($own, 0, $lineStart) : $own, $first?->startsLine() ?? false);
		$tail = $alone ? array_slice($own, $lineStart) : [];
		$indentation = ($tail[0] ?? null)?->is(Trivia::Whitespace) ? $tail[0] : null;
		$moved = [...$moved, ...self::splitComments($tail, atLineStart: true)[1]];
		foreach ($tokens as $token) {
			if ($token !== $first) {
				$moved = [...$moved, ...self::splitComments($token->leadingTrivia, $token->startsLine())[1]];
			}

			if ($token !== $last) {
				$moved = [...$moved, ...self::splitComments($token->trailingTrivia, atLineStart: false)[1]];
			}
		}

		[$trailing, $trailingComments] = self::splitComments($last->trailingTrivia ?? [], atLineStart: false);
		$moved = [...$moved, ...$trailingComments];
		if ($separatorAfter) { // the gap the separator opened goes with it, the line ending of the item stays
			$trailing = array_values(array_filter($trailing, fn(Trivia $trivia) => $trivia->isLineEnding()));
		}

		if ($alone) {
			// a comment that shared the line of the node now stands on a line of its own, indented as the node was
			if ($moved && $first) {
				$moved = self::endComments($indentation ? self::indentComments($moved, $indentation) : $moved, new Trivia(Trivia::LineEnding, self::findLineEnding($first)));
			}

			$trailing = [];

		} else {
			$moved = self::endComments($moved, Trivia::fromText(' '));
			if ($trailingComments) { // the space before a comment goes with it
				$trailing = array_values(array_filter($trailing, fn(Trivia $trivia) => $trivia->isLineEnding()));
			}

			if ($first?->startsLine() && $moved && end($moved)->isLineEnding() && $leading && end($leading)->is(Trivia::Whitespace)) {
				$indent = array_pop($leading); // the next token comes to open the line below the comments, indented as the node was
				$moved = [...self::indentComments($moved, $indent), $indent];
			}
		}

		$neighbor = $previous;
		$previous = $previous && !self::opensOutput($previous) ? $previous : null; // trivia after it would be output
		$before = $after = [];
		if ($comments === CommentPolicy::MoveToPreviousToken && $previous) {
			$before = $moved;
		} elseif ($comments !== CommentPolicy::Drop) {
			$after = $moved;
		}

		if ($previous) {
			$previous->setTrailingTrivia([...$previous->trailingTrivia, ...self::setApart($before, $previous->trailingTrivia), ...$trailing]);
			$trailing = [];
			$ends = $previous->trailingTrivia; // a copy: a private(set) array takes no indirect change from outside
			if ($ends && end($ends)->isLineEnding()) {
				$previous->removeTrailingWhitespace(); // the line ends here, so nothing may dangle before it
			}
		}

		if ($next) {
			$after = $previous ? self::setApart($after, [...$previous->trailingTrivia, ...$leading]) : $after;
			$next->setLeadingTrivia([...$leading, ...$after, ...$trailing, ...$next->leadingTrivia]);
		} elseif ($previous) {
			$previous->setTrailingTrivia([...$previous->trailingTrivia, ...$leading, ...$after]);
		}

		$parent->removeItem($node);

		self::splitSeam($neighbor, $next);
	}


	/**
	 * Sets the comments apart by a space from the token whose trivia they follow, where nothing but another comment
	 * stands between them.
	 * @param  list<Trivia>  $comments
	 * @param  list<Trivia>  $before  the trivia after the token, which the comments follow
	 * @return list<Trivia>
	 */
	private static function setApart(array $comments, array $before): array
	{
		return $comments && $comments[0]->isComment() && (!$before || end($before)->isComment())
			? [Trivia::fromText(' '), ...$comments]
			: $comments;
	}


	/**
	 * Splits the trivia between two neighboring tokens the way the lexer does: the previous token takes them up to and
	 * including its line ending, the next one the rest, an open tag included. A token whose text ends its line or after
	 * which the text is output takes none.
	 */
	private static function splitSeam(?Token $previous, ?Token $next): void
	{
		if ($previous === null || $next === null) {
			return;
		}

		$trivias = [];
		foreach ([...$previous->trailingTrivia, ...$next->leadingTrivia] as $trivia) {
			$last = $trivias ? $trivias[count($trivias) - 1] : null;
			if ($last?->is(Trivia::Whitespace) && $trivia->is(Trivia::Whitespace)) { // one run, as the lexer reads it
				$trivias[count($trivias) - 1] = $last->withText($last->text . $trivia->text);
			} else {
				$trivias[] = $trivia;
			}
		}

		$split = 0;
		if (!self::opensOutput($previous) && !preg_match('~[\r\n]$~D', $previous->text)) {
			foreach ($trivias as $i => $trivia) {
				if ($trivia->is(Trivia::OpenTag)) {
					break;
				}

				$split = $i + 1;
				if ($trivia->isLineEnding()) {
					break;
				}
			}
		}

		$trailing = array_slice($trivias, 0, $split);
		$leading = array_slice($trivias, $split);
		if ($trailing !== $previous->trailingTrivia) {
			$previous->setTrailingTrivia($trailing);
		}

		if ($leading !== $next->leadingTrivia) {
			$next->setLeadingTrivia($leading);
		}
	}


	/**
	 * The line ending of the trivia nearest to the token, before it and then after it, `"\n"` where there is none;
	 * an open tag is no line ending to write, though its text ends with one.
	 */
	private static function findLineEnding(Token $token): string
	{
		foreach ([-1, 1] as $step) {
			for ($current = $token; $current !== null; $current = $step < 0 ? $current->getPrevious() : $current->getNext()) {
				$trivias = [...$current->leadingTrivia, ...$current->trailingTrivia];
				foreach ($step < 0 ? array_reverse($trivias) : $trivias as $trivia) {
					if ($trivia->is(Trivia::LineEnding)) {
						return $trivia->text;
					}
				}
			}
		}

		return "\n";
	}


	/**
	 * Separates comments, each with the line ending directly after it, from the rest of the trivia.
	 * @param  list<Trivia>  $trivias
	 * @return array{list<Trivia>, list<Trivia>}  [rest, comments]
	 */
	private static function splitComments(array $trivias, bool $atLineStart): array
	{
		$rest = $comments = [];
		foreach ($trivias as $i => $trivia) {
			$before = $trivias[$i - 1] ?? null;
			if ($trivia->isComment()) {
				$opensLine = ($trivias[$i - 2] ?? null)?->isLineEnding() ?? $atLineStart;
				if ($opensLine && $before?->is(Trivia::Whitespace)) {
					array_pop($rest); // the comment stands at the start of a line and the whitespace indents it
					$comments[] = $before;
				}

				$comments[] = $trivia;

			} elseif ($trivia->is(Trivia::LineEnding) && $before?->isComment()) {
				$comments[] = $trivia;

			} else {
				$rest[] = $trivia;
			}
		}

		return [$rest, $comments];
	}


	/**
	 * Gives the indentation to each comment that does not start with one: a comment that ended a line and now
	 * opens one, as `splitComments()` hands it over without the whitespace before it.
	 * @param  list<Trivia>  $comments
	 * @return list<Trivia>
	 */
	private static function indentComments(array $comments, Trivia $indentation): array
	{
		$result = [];
		foreach ($comments as $i => $trivia) {
			if ($trivia->isComment() && !($comments[$i - 1] ?? null)?->is(Trivia::Whitespace)) {
				$result[] = $indentation;
			}

			$result[] = $trivia;
		}

		return $result;
	}


	/**
	 * Ends each comment that no line ending follows with the trivia given, a line ending where the comments stand on
	 * lines of their own and a space where they stay on the line of the next token.
	 * @param  list<Trivia>  $comments
	 * @return list<Trivia>
	 */
	private static function endComments(array $comments, Trivia $end): array
	{
		$result = [];
		foreach ($comments as $i => $trivia) {
			$result[] = $trivia;
			if ($trivia->isComment() && !($comments[$i + 1] ?? null)?->is(Trivia::LineEnding)) {
				$result[] = $end;
			}
		}

		return $result;
	}


	/**
	 * Whether nothing but whitespace and comments shares the lines of the node.
	 * @param list<Trivia> $leading
	 * @param list<Trivia> $trailing
	 */
	private static function standsAlone(array $leading, array $trailing, ?Token $previous, ?Token $next): bool
	{
		$startsLine = false;
		for ($i = count($leading) - 1; $i >= 0; $i--) {
			if ($leading[$i]->isLineEnding()) {
				$startsLine = true;
				break;
			} elseif (!$leading[$i]->is(Trivia::Whitespace) && !$leading[$i]->isComment()) {
				return false;
			}
		}

		if (!$startsLine) {
			$before = $previous->trailingTrivia ?? [];
			$startsLine = !$previous || ($before && end($before)->isLineEnding());
		}

		$endsLine = false;
		foreach ($trailing as $trivia) {
			if ($trivia->isLineEnding()) {
				$endsLine = true;
				break;
			} elseif (!$trivia->is(Trivia::Whitespace) && !$trivia->isComment()) {
				return false;
			}
		}

		if (!$endsLine) {
			$after = $next->leadingTrivia ?? [];
			$endsLine = !$next || $next->is(Token::EndOfFile) || ($after && $after[0]->isLineEnding());
		}

		return $startsLine && $endsLine;
	}
}
