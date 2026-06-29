<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\IdentifierNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Nodes\UseItemNode;
use PhpSyntax\SymbolKind;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;
use PhpSyntax\Trivia;
use PhpSyntax\TriviaKind;
use function strlen;


/**
 * use import of classes, functions or constants, written item by item or as a group under a prefix
 * (use A\{B, C};), which fills the slots the other form leaves empty.
 */
final class UseNode extends StatementNode
{
	public const Slots = ['useKeyword', 'type', 'prefix', 'namespaceSeparator', 'openBrace', 'items', 'closeBrace', 'semicolon'];

	/** What the statement imports, which every item without a type of its own imports too. */
	public SymbolKind $kind {
		get => SymbolKind::fromUseType($this->type);
	}


	/** @internal */
	public function __construct(
		public Token $useKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $type { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?NameNode $prefix { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $namespaceSeparator { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<UseItemNode> */
		public SeparatedNodeList $items { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/**
	 * Whether the items are written as a group under a prefix, which every one of them imports.
	 * @phpstan-assert-if-true !null $this->prefix
	 */
	public function isGroup(): bool
	{
		return $this->prefix !== null;
	}


	/**
	 * Adds an import of the fully qualified name, written the way the statement writes its items: whole in
	 * a plain import, under the prefix in a group, which refuses a name standing outside it. The item
	 * imports what the statement imports and goes last unless an index says where.
	 */
	public function addImport(string $name, ?string $alias = null, ?int $index = null): UseItemNode
	{
		$name = ltrim($name, '\\');
		if ($this->isGroup()) {
			$prefix = ltrim($this->prefix->text, '\\') . '\\';
			if (strncasecmp($name, $prefix, strlen($prefix)) !== 0) {
				throw new \InvalidArgumentException("The name '$name' does not stand under the prefix of the group.");
			}

			$name = substr($name, strlen($prefix));
		}

		$item = new UseItemNode(type: null, name: NameNode::fromText($name), asKeyword: null, alias: null);
		if ($alias !== null) {
			$space = [new Trivia(TriviaKind::Whitespace, ' ')];
			$asKeyword = new Token(TokenKind::As, 'as');
			$asKeyword->setTrailingTrivia($space);
			$item->name->token->setTrailingTrivia($space);
			$item->asKeyword = $asKeyword;
			$item->alias = IdentifierNode::fromText($alias);
		}

		$index === null ? $this->items->append($item) : $this->items->insert($index, $item);
		return $item;
	}
}
