<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\SymbolKind;
use PhpSyntax\Token;


/**
 * Imported name with an optional alias; the type (function, const) appears only inside a group use.
 * What the item imports is said by the statement it stands in, the prefix of a group included, so an
 * item is not moved from one statement to another but written anew by UseNode::addImport().
 */
final class UseItemNode extends Node
{
	public const Slots = ['type', 'name', 'asKeyword', 'alias'];

	/**
	 * What the item imports: its own type where a group use writes one per item, else what the statement imports.
	 */
	public SymbolKind $kind {
		get => SymbolKind::fromUseType($this->type ?? $this->getStatement()?->type);
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
	public function __construct(
		public ?Token $type { set => $this->prepareSlot(__PROPERTY__, $value); },
		public NameNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $asKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?IdentifierNode $alias { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/** The import the item belongs to; null for an item that is not in one. */
	public function getStatement(): ?Statement\UseNode
	{
		$statement = $this->parent?->parent;
		return $statement instanceof Statement\UseNode ? $statement : null;
	}
}
