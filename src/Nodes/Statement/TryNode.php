<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{CatchNode, FinallyNode, NodeList, StatementNode};
use PhpSyntax\Token;


/**
 * `try` statement with catches and an optional `finally`.
 */
final class TryNode extends StatementNode
{
	public const Slots = ['tryKeyword', 'body', 'catches', 'finally'];


	/** @internal */
	public function __construct(
		public Token $tryKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public BlockNode $body { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var NodeList<CatchNode> */
		public NodeList $catches { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?FinallyNode $finally { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
