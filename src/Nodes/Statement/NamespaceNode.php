<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{NameNode, PlainNodeList, StatementNode};
use PhpSyntax\Token;


/**
 * `namespace` declaration; after `namespace A;` the following statements are nested in it.
 */
final class NamespaceNode extends StatementNode
{
	public const Slots = ['namespaceKeyword', 'name', 'semicolon', 'openBrace', 'statements', 'closeBrace'];

	public Token $namespaceKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?NameNode $name = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $semicolon = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $openBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var PlainNodeList<StatementNode> */
	public PlainNodeList $statements { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $closeBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param PlainNodeList<StatementNode> $statements
	 */
	public function __construct(
		Token $namespaceKeyword,
		?NameNode $name,
		?Token $semicolon,
		?Token $openBrace,
		PlainNodeList $statements,
		?Token $closeBrace,
	) {
		$this->namespaceKeyword = $namespaceKeyword;
		$name === null || $this->name = $name;
		$semicolon === null || $this->semicolon = $semicolon;
		$openBrace === null || $this->openBrace = $openBrace;
		$this->statements = $statements;
		$closeBrace === null || $this->closeBrace = $closeBrace;
	}
}
