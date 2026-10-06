<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{DeclareItemNode, PlainNodeList, SeparatedNodeList, StatementNode};
use PhpSyntax\Token;


/**
 * `declare` statement in its three forms: with a body, with a bare semicolon, or with the alternative syntax.
 */
final class DeclareNode extends StatementNode
{
	public const Slots = ['declareKeyword', 'openParen', 'items', 'closeParen', 'body', 'colon', 'statements', 'endKeyword', 'semicolon'];

	public Token $declareKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<DeclareItemNode> */
	public SeparatedNodeList $items { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?StatementNode $body = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $colon = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var ?PlainNodeList<StatementNode> */
	public ?PlainNodeList $statements = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $endKeyword = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $semicolon = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param SeparatedNodeList<DeclareItemNode> $items
	 * @param ?PlainNodeList<StatementNode> $statements
	 */
	public function __construct(
		Token $declareKeyword,
		Token $openParen,
		SeparatedNodeList $items,
		Token $closeParen,
		?StatementNode $body,
		?Token $colon,
		?PlainNodeList $statements,
		?Token $endKeyword,
		?Token $semicolon,
	) {
		$this->declareKeyword = $declareKeyword;
		$this->openParen = $openParen;
		$this->items = $items;
		$this->closeParen = $closeParen;
		$body === null || $this->body = $body;
		$colon === null || $this->colon = $colon;
		$statements === null || $this->statements = $statements;
		$endKeyword === null || $this->endKeyword = $endKeyword;
		$semicolon === null || $this->semicolon = $semicolon;
	}


	/** The directive of the name given, such as `strict_types`, in any case as PHP reads it; null where none is. */
	public function findDirective(string $name): ?DeclareItemNode
	{
		return array_find($this->items->getItems(), fn(DeclareItemNode $item) => $item->name->equals($name));
	}
}
