<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Token;
use PhpSyntax\TokenIndex;


/**
 * Root of the tree: the statements of a file and the end-of-file token carrying the trailing trivia.
 */
final class FileNode extends Node
{
	public const Slots = ['statements', 'endOfFile'];

	/** version of the tree: every write to a slot, a list, or the text or trivia of a token increments it */
	public int $revision = 0;

	private ?TokenIndex $index = null;


	/**
	 * @internal
	 */
	public function __construct(
		/** @var NodeList<StatementNode> */
		public NodeList $statements { set => $this->statements = $this->prepareSlot(__PROPERTY__, $value); },
		public Token $endOfFile { set => $this->endOfFile = $this->prepareSlot(__PROPERTY__, $value); },
	) {
		$this->revision = 0; // the hooks counted the construction
	}


	/**
	 * A structural mutation: the children reported by adopted() and released() change places, and the index
	 * moves their tokens at its next query instead of rebuilding the order.
	 * @internal called by Node::structureChanged()
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


	/** @internal called by Node::adopt() */
	public function adopted(Node|Token $child): void
	{
		$this->index?->adopted($child);
	}


	/** @internal called by Node::release() while the child is still in the tree */
	public function released(Node|Token $child): void
	{
		$this->index?->released($child);
	}


	public function getIndex(): TokenIndex
	{
		return $this->index ??= new TokenIndex($this);
	}


	public function getChildren(): array
	{
		return [$this->statements, $this->endOfFile];
	}


	public function replaceChild(Node|Token $old, Node|Token $new): void
	{
		if ($old === $this->statements && $new instanceof NodeList) {
			$this->setStatements($new);
		} elseif ($old === $this->endOfFile && $new instanceof Token) {
			$this->setEndOfFile($new);
		} else {
			throw self::describeChildMismatch($old);
		}
	}


	/** @param NodeList<StatementNode> $statements */
	public function setStatements(NodeList $statements): void
	{
		$this->setSlot('statements', $statements);
	}


	public function setEndOfFile(Token $endOfFile): void
	{
		$this->setSlot('endOfFile', $endOfFile);
	}
}
