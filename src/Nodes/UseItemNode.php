<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{CommentPolicy, Node, SymbolKind, Token};
use function count;


/**
 * Imported name with an optional alias; the type (`function`, `const`) appears only inside a group use.
 * What the item imports is said by the statement it stands in, the prefix of a group included, so an
 * item is not moved from one statement to another but written anew by `UseNode::addImport()`.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class UseItemNode extends Node
{
	public const Slots = ['kindKeyword', 'name', 'asKeyword', 'alias'];

	public ?Token $kindKeyword = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public NameNode $name { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $asKeyword = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?IdentifierNode $alias = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/**
	 * What the item imports: its own type where a group use writes one per item, else what the statement imports.
	 */
	public SymbolKind $symbolKind {
		get => SymbolKind::fromKeyword($this->kindKeyword ?? $this->getStatement()?->kindKeyword);
	}

	/**
	 * The name the item imports, without a leading backslash: what is written here, and in a group use
	 * the prefix of the statement before it.
	 */
	public string $fullName {
		get {
			$prefix = $this->getStatement()?->prefix;
			return ltrim($prefix === null ? $this->name->text : $prefix->text . '\\' . $this->name->text, '\\');
		}
	}


	/** @internal */
	public function __construct(?Token $kindKeyword, NameNode $name, ?Token $asKeyword, ?IdentifierNode $alias)
	{
		$kindKeyword === null || $this->kindKeyword = $kindKeyword;
		$this->name = $name;
		$asKeyword === null || $this->asKeyword = $asKeyword;
		$alias === null || $this->alias = $alias;
	}


	/**
	 * Removes the item as `Node::remove()` does, and the whole statement where the item is the only one it imports,
	 * which `StatementNode::remove()` takes with its line.
	 */
	public function remove(CommentPolicy $comments = CommentPolicy::MoveToNextToken, bool $mergeBlankLines = false): void
	{
		$statement = $this->getStatement();
		if ($statement !== null && count($statement->items) === 1) {
			$statement->remove($comments, $mergeBlankLines);
		} else {
			parent::remove($comments, $mergeBlankLines);
		}
	}


	/** The import the item belongs to; null for an item that is not in one. */
	public function getStatement(): ?Statement\UseNode
	{
		$statement = $this->parent?->parent;
		return $statement instanceof Statement\UseNode ? $statement : null;
	}
}
