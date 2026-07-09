<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token, TokenIndex};


/**
 * Root of the tree: the statements of a file and the end-of-file token carrying the trailing trivia.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class FileNode extends Node
{
	public const Slots = ['statements', 'endOfFile'];

	/** version of the tree: every write to a slot, a list, or the text or trivia of a token that changes it increments it */
	public private(set) int $revision = 0;

	private ?TokenIndex $index = null;


	/**
	 * @internal
	 */
	public function __construct(
		/** @var PlainNodeList<StatementNode> */
		public PlainNodeList $statements { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $endOfFile { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
		$this->revision = 0; // the hooks counted the construction
	}


	/**
	 * A file stands under no node, yet it is the one to report to, so every write takes the whole way.
	 * @template T of Node|Token|null
	 * @param  T  $value
	 * @return T
	 */
	protected function prepareSlot(string $slot, Node|Token|null $value): Node|Token|null
	{
		$old = $this->$slot ?? null;
		if ($value === $old) {
			return $value;
		} elseif ($value !== null) {
			$this->prepareValue($value, $old);
		}

		$this->exchangeChild($old, $value);
		return $value;
	}


	/**
	 * A structural mutation: the children reported by `adopted()` and `released()` change places, and the index
	 * moves their tokens at its next query instead of rebuilding the order.
	 * @internal called by `Node::structureChanged()`
	 */
	public function structureChanged(): void
	{
		$this->revision++;
		$this->index?->structureChanged();
	}


	/**
	 * The text or trivia of a token changed: the lines after it move by the line endings it gained or lost,
	 * and with a change before the token so does its own.
	 * @internal called by the setters of Token
	 */
	public function tokenChanged(Token $token, int $lineEndings, bool $leading): void
	{
		$this->revision++;
		$this->index?->updateToken($token, $lineEndings, $leading);
	}


	/** @internal called by `Node::adopt()` */
	public function adopted(Node|Token $child): void
	{
		$this->index?->adopted($child);
	}


	/** @internal called by `Node::release()` while the child is still in the tree */
	public function released(Node|Token $child): void
	{
		$this->index?->released($child);
	}


	/** @internal the order and the positions of the tokens, which the tokens and the nodes ask */
	public function getIndex(): TokenIndex
	{
		return $this->index ??= new TokenIndex($this);
	}


	/** The tokens of the file in source order, kept by the index rather than collected anew. */
	public function getTokens(): array
	{
		return $this->getIndex()->getTokens();
	}


	/**
	 * The outermost node of the class whose text stands exactly at the byte offsets of the current text, the end
	 * exclusive; the way a position of another tool (a parser of its own, an editor) is brought to the tree.
	 * @template T of Node
	 * @param  class-string<T>  $class
	 * @return ?T
	 */
	public function findNode(int $start, int $end, string $class = Node::class): ?Node
	{
		return $this->getIndex()->findNode($start, $end, $class);
	}


	public function __clone()
	{
		$this->index = null; // before the children are written, so that their hooks do not report to the index of the original
		parent::__clone();
	}
}
