<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Nodes\Statement\BlockNode;
use PhpSyntax\Token;


/**
 * finally clause.
 */
final class FinallyNode extends Node
{
	public const Slots = ['finallyKeyword', 'body'];


	/** @internal */
	public function __construct(
		public Token $finallyKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public BlockNode $body { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
