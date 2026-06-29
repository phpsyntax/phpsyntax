<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Token;


/**
 * Parenthesized arguments of a call, instantiation, attribute or exit.
 */
final class ArgumentListNode extends Node
{
	public const Slots = ['openParen', 'items', 'closeParen'];


	/** @internal */
	public function __construct(
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ArgumentNode|VariadicPlaceholderNode> */
		public SeparatedNodeList $items { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/** Whether the call is written f(...), which makes a closure of it instead of calling it. */
	public function isPartialApplication(): bool
	{
		return ($this->items->getItems()[0] ?? null) instanceof VariadicPlaceholderNode;
	}


	/**
	 * The argument the parameter of the name and the position gets: the one written with the name, else the
	 * one standing at the position, so that get_class(object: $o) reads as the call get_class($o) is. Null
	 * where the parameter gets none, and where the call does not say which it gets: an unpacked array stands
	 * for as many arguments as it holds, so it takes the answer from a position after it, never from a name,
	 * which stands for itself.
	 */
	public function findArgument(string $name, int $position): ?ArgumentNode
	{
		$positional = null;
		$index = 0;
		$unpacked = false;
		foreach ($this->items as $argument) {
			if (!$argument instanceof ArgumentNode) {
				continue;
			} elseif ($argument->name?->text === $name) {
				return $argument;
			} elseif ($argument->ellipsis !== null) {
				$unpacked = true; // what it unpacks is unknown, so no position after it has an answer
			} elseif (!$unpacked && $argument->name === null && $index++ === $position) {
				$positional = $argument;
			}
		}

		return $positional;
	}
}
