<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Token;


/**
 * The use (...) clause of a closure.
 */
final class ClosureUsesNode extends Node
{
	public const Slots = ['useKeyword', 'openParen', 'variables', 'closeParen'];


	/** @internal */
	public function __construct(
		public Token $useKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ClosureUseNode> */
		public SeparatedNodeList $variables { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
