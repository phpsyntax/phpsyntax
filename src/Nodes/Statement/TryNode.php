<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{CatchNode, FinallyNode, PlainNodeList, StatementNode};
use PhpSyntax\Token;


/**
 * `try` statement with catches and an optional `finally`.
 */
final class TryNode extends StatementNode
{
	public const Slots = ['tryKeyword', 'body', 'catches', 'finally'];

	public Token $tryKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public BlockNode $body { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var PlainNodeList<CatchNode> */
	public PlainNodeList $catches { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?FinallyNode $finally = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param PlainNodeList<CatchNode> $catches
	 */
	public function __construct(Token $tryKeyword, BlockNode $body, PlainNodeList $catches, ?FinallyNode $finally)
	{
		$this->tryKeyword = $tryKeyword;
		$this->body = $body;
		$this->catches = $catches;
		$finally === null || $this->finally = $finally;
	}
}
