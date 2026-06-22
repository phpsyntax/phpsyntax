<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Node;
use PhpSyntax\Nodes\ArrayItemNode;
use PhpSyntax\Nodes\EmptyArrayItemNode;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Token;


/**
 * Destructuring, written `list(...)` or `[...]`; the keyword is null for the short form. A short array
 * is a literal until it stands where a place is assigned to, which is where it becomes one of these.
 * It is no expression: it never carries a value.
 */
final class ListNode extends Node
{
	public const Slots = ['listKeyword', 'openDelimiter', 'items', 'closeDelimiter'];


	/** @internal */
	public function __construct(
		public ?Token $listKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openDelimiter { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ArrayItemNode|EmptyArrayItemNode> */
		public SeparatedNodeList $items { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeDelimiter { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
