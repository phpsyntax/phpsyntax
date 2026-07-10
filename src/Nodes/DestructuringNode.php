<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * Destructuring, written `list(...)` or `[...]`; the keyword is null for the short form. A short array
 * is a literal until it stands where a place is assigned to, which is where it becomes one of these.
 * It is no expression: it never carries a value.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class DestructuringNode extends Node
{
	public const Slots = ['listKeyword', 'openDelimiter', 'items', 'closeDelimiter'];

	public ?Token $listKeyword = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openDelimiter { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var SeparatedNodeList<ArrayItemNode|SkippedArrayItemNode> */
	public SeparatedNodeList $items { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeDelimiter { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param SeparatedNodeList<ArrayItemNode|SkippedArrayItemNode> $items
	 */
	public function __construct(?Token $listKeyword, Token $openDelimiter, SeparatedNodeList $items, Token $closeDelimiter)
	{
		$listKeyword === null || $this->listKeyword = $listKeyword;
		$this->openDelimiter = $openDelimiter;
		$this->items = $items;
		$this->closeDelimiter = $closeDelimiter;
	}


	/**
	 * The destructuring a target is: a short array written where a place is assigned to means the same as
	 * `list(...)` and becomes one, its own items included, however deep they nest. Anything else stands as it is,
	 * a long array among it, which PHP does not take for a target.
	 * @internal what the parser and `Builder::assign()` make of a target
	 */
	public static function destructure(ExpressionNode|self $target): ExpressionNode|self
	{
		if ($target instanceof self) {
			self::destructureItems($target->items);
			return $target;

		} elseif (!$target instanceof Expression\ArrayNode || $target->arrayKeyword !== null) {
			return $target;
		}

		[$open, $items, $close] = [$target->openDelimiter, $target->items, $target->closeDelimiter];
		$target->dismantle(); // the array is dropped and its parts go on
		self::destructureItems($items);
		return new self(null, $open, $items, $close);
	}


	/** @param SeparatedNodeList<ArrayItemNode|SkippedArrayItemNode> $items */
	private static function destructureItems(SeparatedNodeList $items): void
	{
		foreach ($items->getItems() as $item) {
			if ($item instanceof ArrayItemNode && ($value = self::destructure($item->value)) !== $item->value) {
				$item->value = $value;
			}
		}
	}
}
