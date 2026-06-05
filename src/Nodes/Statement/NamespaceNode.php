<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{NameNode, NodeList, StatementNode};
use PhpSyntax\Token;


/**
 * `namespace` declaration; after `namespace A;` the following statements are nested in it.
 */
final class NamespaceNode extends StatementNode
{
	public const Slots = ['namespaceKeyword', 'name', 'semicolon', 'openBrace', 'statements', 'closeBrace'];


	/** @internal */
	public function __construct(
		public Token $namespaceKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?NameNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var NodeList<StatementNode> */
		public NodeList $statements { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
