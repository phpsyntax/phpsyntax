<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;

use PhpSyntax\Nodes\Expression\{ArrowFunctionNode, ClosureNode};
use PhpSyntax\Nodes\Member\{MethodNode, PropertyNode};
use PhpSyntax\Nodes\{NodeList, ParameterNode, SeparatedNodeList, TypeNode};
use PhpSyntax\Nodes\Statement\FunctionNode;
use function count;


/**
 * Takes a node out of the tree, puts another in its place or writes a slot that comes with a token of its own, and
 * sews up the trivia around the cut: the comments, the lines and the whitespace the node leaves behind or the new
 * one is given. It also takes a node a write is given, a copy of one standing in a tree.
 * @internal the algorithm of `Node::remove()`, `Node::replaceWith()`, `Token::replaceWith()`, `BlockNode::unwrap()`,
 *   `SeparatedNodeList::setTrailingSeparator()` and the setters of a type
 */
final class Surgery
{
	/** See `FunctionLikeNode::setReturnType()`; the anchor is the token the return type follows. */
	public static function writeReturnType(
		FunctionNode|MethodNode|ClosureNode|ArrowFunctionNode $node,
		Token $anchor,
		?TypeNode $type,
	): void
	{
		$current = $node->returnType;
		if ($type === $current) {
			return;

		} elseif ($type === null) {
			if ($current === null || $node->colon === null) {
				return;
			}

			$tokens = [$node->colon, ...$current->getTokens()];
			$next = self::findNeighbor($tokens[count($tokens) - 1], 1);
			$gap = self::collapseGap($anchor, $tokens, beforeBody: true);
			$node->colon = null;
			$node->returnType = null;
			$anchor->setTrailingTrivia([]);
			self::closeGap($anchor, $gap, $next);
			return;
		}

		$type = self::take($type);
		if ($current !== null) {
			$current->replaceWith($type);
			return;
		}

		$trailing = $anchor->trailingTrivia;
		$node->colon = Token::fromText(':')->setTrailingTrivia([Trivia::fromText(' ')]);
		$node->returnType = $type;
		$anchor->setTrailingTrivia([]);
		$type->setEdgeTrivia(null, $trailing); // the gap before the body stays where it was
	}


	/** See `ParameterNode::setType()` and `PropertyNode::setType()`. */
	public static function writeType(ParameterNode|PropertyNode $node, ?TypeNode $type): void
	{
		$current = $node->type;
		if ($type === $current) {
			return;
		}

		$next = null;
		foreach (array_slice($node::Slots, array_search('type', $node::Slots, true) + 1) as $slot) {
			if ($next = $node->$slot?->getFirstToken()) {
				break;
			}
		}

		if ($type === null) {
			$tokens = $current?->getTokens();
			if (!$tokens) {
				return;
			}

			$previous = self::findNeighbor($tokens[0], -1);
			$gap = self::collapseGap($previous, $tokens, beforeBody: false);
			$node->type = null;
			$previous?->setTrailingTrivia([]);
			self::closeGap($previous, $gap, $next);
			return;
		}

		$type = self::take($type);
		if ($current !== null) {
			$current->replaceWith($type);
			return;
		}

		$leading = $next === null ? [] : $next->leadingTrivia;
		$node->type = $type;
		$next?->setLeadingTrivia([]);
		$type->setEdgeTrivia($leading, [Trivia::fromText(' ')]);
	}


	/**
	 * The trivia a run of tokens leaves between the previous token and the next one once it goes, as one gap: what
	 * stood before the run, then its comments, each followed by a space or by its line ending, then the end of its
	 * line; the whitespace collapses, and a run alone on its line takes the line with it. The whitespace that ended the
	 * run stays only with `$beforeBody`, being the gap before the body of a function, which also sets a comment kept
	 * right after the previous token apart from it by a space.
	 * @param  non-empty-list<Token>  $tokens
	 * @return list<Trivia>
	 */
	private static function collapseGap(?Token $previous, array $tokens, bool $beforeBody): array
	{
		$before = [...$previous->trailingTrivia ?? [], ...$tokens[0]->leadingTrivia];
		$after = $tokens[count($tokens) - 1]->trailingTrivia;
		$comments = [];
		foreach ($tokens as $i => $token) {
			$trivias = $i === 0 ? $token->trailingTrivia : [...$token->leadingTrivia, ...$token->trailingTrivia];
			foreach ($trivias as $j => $trivia) {
				if ($trivia->isComment()) {
					$following = $trivias[$j + 1] ?? null;
					$comments[] = $trivia;
					$comments[] = $following?->is(Trivia::LineEnding) ? $following : Trivia::fromText(' ');
				}
			}
		}

		$lastComment = array_find_key(array_reverse($after, preserve_keys: true), fn(Trivia $trivia) => $trivia->isComment());
		$after = $lastComment === null ? $after : array_slice($after, $lastComment + 1); // what ends the run
		$end = array_find($after, fn(Trivia $trivia) => $trivia->is(Trivia::LineEnding))
			?? (array_find($after, fn(Trivia $trivia) => $trivia->is(Trivia::Whitespace)) ? Trivia::fromText(' ') : null);
		if ($comments && end($comments)->is(Trivia::Whitespace)) {
			if ($end) {
				$comments[count($comments) - 1] = $end;
			} else {
				array_pop($comments); // the run stood right against the next token, and so does its last comment
			}
		}

		$lastBreak = null;
		foreach ($before as $i => $trivia) {
			if ($trivia->isLineEnding()) {
				$lastBreak = $i;
			} elseif (!$trivia->is(Trivia::Whitespace)) {
				$lastBreak = null;
			}
		}

		if ($lastBreak !== null && $end?->is(Trivia::LineEnding)) { // the run stood alone on its line
			$indentation = array_slice($before, $lastBreak + 1);
			return [...array_slice($before, 0, $lastBreak + 1), ...($comments ? [...$indentation, ...$comments] : [])];
		}

		if ($beforeBody && $comments && (!$before || end($before)->isComment())) {
			$before[] = Trivia::fromText(' ');
		}

		$result = [];
		foreach ([...$before, ...($comments ?: ($end && ($beforeBody || $end->is(Trivia::LineEnding)) ? [$end] : []))] as $trivia) {
			$last = $result ? $result[count($result) - 1] : null;
			if ($trivia->is(Trivia::Whitespace) && $last?->is(Trivia::Whitespace)) {
				continue;
			} elseif ($trivia->is(Trivia::LineEnding) && $last?->is(Trivia::Whitespace)) {
				array_pop($result);
			}

			$result[] = $trivia;
		}

		return $result;
	}


	/**
	 * A node standing in a tree as a copy without the trivia on its edges, a detached one as it is with its edges cleared.
	 * @template T of Node
	 * @param  T  $node
	 * @return T
	 */
	public static function take(Node $node): Node
	{
		return $node->parent !== null ? $node->withoutEdgeTrivia() : $node->setEdgeTrivia([], []);
	}


	/**
	 * See `SeparatedNodeList::setTrailingSeparator()`; `$last` is the last token of the last item, and the separator
	 * taken out and the one put in are already written into the list. A doc comment after the last item, on its line
	 * or before the token after the list, stays before the separator, because PHP reads it as the documentation of
	 * a parameter only there.
	 */
	public static function moveSeparatorTrivia(?Token $last, ?Token $current, ?Token $separator): void
	{
		if ($current === null) {
			if ($separator && $last && !$separator->leadingTrivia && !$separator->trailingTrivia) {
				$next = self::findNeighbor($separator, 1);
				$leading = $next === null ? [] : $next->leadingTrivia;
				$doc = array_find_key(array_reverse($leading, preserve_keys: true), fn(Trivia $trivia) => $trivia->is(Trivia::DocComment));
				if ($next && $doc !== null) { // the separator goes after it and takes the rest of its line as the lexer would
					$lineEnding = array_find_key(array_slice($leading, $doc + 1, preserve_keys: true), fn(Trivia $trivia) => $trivia->isLineEnding());
					$end = $lineEnding === null ? count($leading) : $lineEnding + 1;
					$separator->setLeadingTrivia(array_slice($leading, 0, $doc + 1))->setTrailingTrivia(array_slice($leading, $doc + 1, $end - $doc - 1));
					$next->setLeadingTrivia(array_slice($leading, $end));
					return;
				}

				$trailing = $last->trailingTrivia;
				$kept = 0;
				foreach ($trailing as $i => $trivia) {
					if ($trivia->is(Trivia::DocComment)) {
						$kept = $i + 1;
					} elseif (!$trivia->is(Trivia::Whitespace)) {
						break;
					}
				}

				$separator->setTrailingTrivia(array_slice($trailing, $kept));
				$last->setTrailingTrivia(array_slice($trailing, 0, $kept));
			}

			return;

		} elseif ($separator && !$separator->leadingTrivia && !$separator->trailingTrivia) {
			$separator->setLeadingTrivia($current->leadingTrivia)->setTrailingTrivia($current->trailingTrivia);
			return;
		}

		$before = $last === null ? [] : $last->trailingTrivia;
		$opensLine = $before && end($before)->isLineEnding();
		if ($separator === null) {
			$leading = $current->leadingTrivia;
			$trailing = $current->trailingTrivia;
			if ($opensLine) { // the line of the separator goes, unless a comment stands on it
				while (
					!array_any($leading, fn(Trivia $trivia) => $trivia->isComment())
					&& $trailing
					&& $trailing[0]->is(Trivia::Whitespace)
				) {
					array_shift($trailing);
				}

				$gap = array_any([...$leading, ...$trailing], fn(Trivia $trivia) => $trivia->isComment()) ? [...$leading, ...$trailing] : [];

			} else {
				$gap = [...self::splitComments($leading, atLineStart: false)[1], ...$trailing];
			}

			self::closeGap($last, $gap, $last ? self::findNeighbor($last, 1) : null);
			return;
		}

		[, $comments] = self::splitComments($current->leadingTrivia, $opensLine);

		// a separator with trivia of its own keeps them, followed by what the old one carried beyond whitespace
		$kept = $current->trailingTrivia;
		while ($kept && $kept[0]->is(Trivia::Whitespace)) {
			array_shift($kept);
		}

		if ($kept) {
			$own = $separator->trailingTrivia;
			if (array_any($kept, fn(Trivia $trivia) => $trivia->isLineEnding())) {
				$own = array_values(array_filter($own, fn(Trivia $trivia) => !$trivia->isLineEnding()));
			}

			if ($kept[0]->isLineEnding() && $own && end($own)->is(Trivia::Whitespace)) {
				array_pop($own);
			} elseif ($kept[0]->isComment() && (!$own || !end($own)->is(Trivia::Whitespace))) {
				$own[] = Trivia::fromText(' ');
			}

			$separator->setTrailingTrivia([...$own, ...$kept]);
		}

		if ($comments) {
			$separator->setLeadingTrivia([...$comments, ...$separator->leadingTrivia]);
		}
	}


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
	public static function opensOutput(Token $token): bool
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
	public static function remove(Node $node, CommentPolicy $comments, bool $mergeBlankLines = false): void
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
		if ($previous === null && $next === null) { // a subtree without a file, where nothing around takes the trivia
			$parent->removeItem($node);
			return;
		}

		$start = $node->getFirstToken();
		$startLeading = $start === null ? [] : $start->leadingTrivia;
		$cut = self::findPreambleEnd($startLeading);
		$preamble = array_slice($startLeading, 0, $cut); // it stays where it stands, whatever the policy
		$blankLines = $lineEnding = null;
		if ($mergeBlankLines && $start && $next && Indentation::opensLine($start) && Indentation::opensLine($next)) {
			$above = $cut ? self::countLeadingLineEndings(array_slice($startLeading, $cut)) : $start->countBlankLinesBefore();
			$blankLines = self::countMergedBlankLines($node, $parent, $above, $next);
			$lineEnding = self::findLineEnding($start);
		}

		$alone = self::standsAlone($first->leadingTrivia ?? [], $last->trailingTrivia ?? [], $previous, $next);
		$own = $first === $start ? array_slice($startLeading, $cut) : ($first->leadingTrivia ?? []);
		$lineStart = 0; // where the line of the node starts in its leading trivia, the head being the lines above it
		foreach ($alone ? $own : [] as $i => $trivia) {
			if ($trivia->isLineEnding()) {
				$lineStart = $i + 1;
			}
		}

		$head = $alone ? array_slice($own, 0, $lineStart) : $own;
		[$leading, $moved] = self::splitComments($head, $first?->startsLine() ?? false);
		$below = 0; // blank lines between the last comment above the node and the node, which stay below the comment
		if ($lineEnding !== null) { // the lines above the comments are set anew by the merged count, those between them stay
			$commentKeys = array_keys(array_filter($head, fn(Trivia $trivia) => $trivia->isComment()));
			if ($commentKeys) {
				$from = $commentKeys[0] - (($head[$commentKeys[0] - 1] ?? null)?->is(Trivia::Whitespace) ? 1 : 0);
				$to = end($commentKeys) + (($head[end($commentKeys) + 1] ?? null)?->is(Trivia::LineEnding) ? 2 : 1);
				$moved = array_slice($head, $from, $to - $from);
				$below = count(array_filter(array_slice($head, $to), fn(Trivia $trivia) => $trivia->is(Trivia::LineEnding)));
				if ($parent->indexOf($node) === count($parent) - 1) { // the gap toward the end of the list is the merged one
					$below = min($below, $blankLines);
				}

				$head = [...array_slice($head, 0, $from), ...array_slice($head, $to)];
			}

			$leading = array_values(array_filter($head, fn(Trivia $trivia) => !$trivia->isWhitespace()));
		}

		$tail = $alone ? array_slice($own, $lineStart) : [];
		$indentation = ($tail[0] ?? null)?->is(Trivia::Whitespace) ? $tail[0] : null;
		$moved = [...$moved, ...self::splitComments($tail, atLineStart: true)[1]];
		foreach ($tokens as $token) {
			if ($token !== $first) {
				$trivias = $token === $start ? array_slice($startLeading, $cut) : $token->leadingTrivia;
				$moved = [...$moved, ...self::splitComments($trivias, $token->startsLine())[1]];
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

		if ($lineEnding !== null) {
			$next->setBlankLinesBefore(0, $lineEnding); // they are set anew above what the node leaves to it
		}

		$neighbor = $previous;
		$previous = $previous && !self::opensOutput($previous) ? $previous : null; // trivia after it would be output
		$before = $after = [];
		if ($comments === CommentPolicy::MoveToPreviousToken && $previous) {
			$before = $moved;
		} elseif ($comments !== CommentPolicy::StayWithNode) {
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
			$gap = $after && $below ? array_fill(0, $below, new Trivia(Trivia::LineEnding, (string) $lineEnding)) : [];
			$after = $previous ? self::setApart($after, [...$previous->trailingTrivia, ...$leading]) : $after;
			$next->setLeadingTrivia([...$leading, ...$after, ...$gap, ...$trailing, ...$next->leadingTrivia]);
		} elseif ($previous) {
			$previous->setTrailingTrivia([...$previous->trailingTrivia, ...$leading, ...$after]);
		}

		$parent->removeItem($node);
		if ($preamble && $next) { // above what the node leaves to the next token, the merged blank lines below it
			$rest = $next->leadingTrivia;
			if ($blankLines !== null) {
				$blank = array_fill(0, $blankLines, new Trivia(Trivia::LineEnding, (string) $lineEnding));
				$rest = [...$blank, ...array_slice($rest, self::countLeadingLineEndings($rest))];
			}

			$next->setLeadingTrivia([...$preamble, ...$rest]);

		} elseif ($blankLines !== null && $next->startsLine()) {
			$next->setBlankLinesBefore($blankLines, $lineEnding);
		}

		self::splitSeam($neighbor, $next);
		if ($comments === CommentPolicy::StayWithNode) {
			self::keepOwnedComments($start, $cut);
		} else {
			self::takeOutComments($node);
		}
	}


	/**
	 * Where the preamble ends among the trivia before the first token of a node: the comment lines above the comments
	 * the node owns, which are the block of comment lines right above it and the block holding its doc comment, with
	 * everything below them. A preamble, a header of the file or of a section, belongs to no node; it ends with the line
	 * ending of its last comment, the blank lines below it being the gap above the node. Zero where there is none.
	 * @param  list<Trivia>  $trivias
	 */
	private static function findPreambleEnd(array $trivias): int
	{
		$lines = []; // each line above the node: where it ends, whether it holds a comment, whether a block may hold it
		$doc = null;
		$from = 0;
		foreach ($trivias as $i => $trivia) {
			if ($trivia->is(Trivia::DocComment)) {
				$doc = count($lines);
			}

			if ($trivia->isLineEnding()) {
				$line = array_slice($trivias, $from, $i + 1 - $from);
				$comment = array_any($line, fn(Trivia $trivia) => $trivia->isComment());
				$lines[] = [$i + 1, $comment, $comment && !array_any($line, fn(Trivia $trivia) => $trivia->is(Trivia::OpenTag))];
				$from = $i + 1;
			}
		}

		$owned = count($lines);
		foreach ([count($lines) - 1, $doc === count($lines) ? null : $doc] as $anchor) { // a doc comment on the line of the node is its own anyway
			if ($anchor !== null && ($lines[$anchor][2] ?? false)) {
				while ($anchor > 0 && $lines[$anchor - 1][2]) {
					$anchor--;
				}

				$owned = min($owned, $anchor);
			}
		}

		for ($i = $owned - 1; $i >= 0; $i--) {
			if ($lines[$i][1]) {
				return $lines[$i][0];
			}
		}

		return 0;
	}


	/**
	 * Leaves to a node taken out with its comments the trivia before it that it owns: its comments with their lines and
	 * its indentation, without the preamble, the open tag and the blank lines above them, which belong to the place.
	 */
	private static function keepOwnedComments(?Token $start, int $cut): void
	{
		$trivias = $start === null ? [] : array_slice($start->leadingTrivia, $cut);
		if (($trivias[0] ?? null)?->is(Trivia::OpenTag)) {
			array_shift($trivias);
		}

		$start?->setLeadingTrivia(array_slice($trivias, self::countLeadingLineEndings($trivias)));
	}


	/**
	 * The number of line endings the trivia start with, the blank lines above what follows them.
	 * @param  list<Trivia>  $trivias
	 */
	private static function countLeadingLineEndings(array $trivias): int
	{
		$count = 0;
		while (($trivias[$count] ?? null)?->is(Trivia::LineEnding)) {
			$count++;
		}

		return $count;
	}


	/**
	 * Takes the comments the tree kept out of a node taken out without them, so that they stand in one place: its edges
	 * are cleared and the comments inside it removed, but for those of a string, which are its text.
	 */
	private static function takeOutComments(Node $node): void
	{
		$node->setEdgeTrivia([], []);
		foreach ($node->getTokens() as $token) {
			foreach ([...$token->leadingTrivia, ...$token->trailingTrivia] as $trivia) {
				if ($trivia->isComment() && !$trivia->inInterpolation) {
					$token->removeTrivia($trivia);
				}
			}
		}
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
	public static function splitSeam(?Token $previous, ?Token $next): void
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
	 * Moves the comments of a token about to go to the token after it, see `BlockNode::unwrap()`: one on a line of its
	 * own and one ending the line of the token stand on lines of their own there, indented as the token was, the token
	 * keeping its line ending, and one before the next token on the same line stays before it.
	 */
	public static function moveCommentsToNext(Token $token): void
	{
		$next = self::findNeighbor($token, 1);
		[$leading, $above] = self::splitComments($token->leadingTrivia, $token->startsLine());
		[$trailing, $after] = self::splitComments($token->trailingTrivia, atLineStart: false);
		if ($next === null || (!$above && !$after)) {
			return;
		}

		$lineEnding = array_find($token->trailingTrivia, fn(Trivia $trivia) => $trivia->is(Trivia::LineEnding));
		if ($lineEnding) {
			$indentation = $leading && end($leading)->is(Trivia::Whitespace) && $token->startsLine() ? end($leading) : null;
			$above = self::endComments($above, $lineEnding);
			$after = $indentation ? self::indentComments($after, $indentation) : $after;
			$trailing = [$lineEnding];
		} else {
			$above = self::endComments($above, Trivia::fromText(' '));
			$after = self::endComments($after, Trivia::fromText(' '));
			$trailing = []; // the comments bring the space before the next token
		}

		$moved = [...$above, ...$after];
		if ($next->is(Token::EndOfFile) && $moved && end($moved)->is(Trivia::Whitespace)) {
			array_pop($moved); // nothing follows that a space would set apart
		}

		$token->setLeadingTrivia($leading)->setTrailingTrivia($trailing);
		$next->setLeadingTrivia([...$moved, ...$next->leadingTrivia]);
	}


	/**
	 * The blank lines left between the neighbors of a node that goes, both standing on lines of their own: the narrower
	 * of the two gaps it stood between, and the one toward the edge where it was the first or the last item of its list.
	 * @param  NodeList<covariant Node>  $list
	 * @param  int  $above  the blank lines above the node, below a preamble it leaves in place
	 */
	private static function countMergedBlankLines(Node $node, NodeList $list, int $above, Token $next): int
	{
		$index = $list->indexOf($node);
		$last = count($list) - 1;
		return match (true) {
			$index === 0 && $last > 0 => $above,
			$index === $last && $last > 0 => $next->countBlankLinesBefore(),
			default => min($above, $next->countBlankLinesBefore()),
		};
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
