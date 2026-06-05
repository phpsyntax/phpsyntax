<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * Item of an array literal or a destructuring list: value with optional key, by reference or unpacked.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class ArrayItemNode extends Node
{
	public const Slots = ['key', 'doubleArrow', 'ampersand', 'ellipsis', 'value'];

	public ?ExpressionNode $key = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $doubleArrow = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $ampersand = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $ellipsis = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode|DestructuringNode $value { set => $this->prepareSlot(__PROPERTY__, $value); }


	/** @internal */
	public function __construct(
		?ExpressionNode $key,
		?Token $doubleArrow,
		?Token $ampersand,
		?Token $ellipsis,
		ExpressionNode|DestructuringNode $value,
	) {
		$key === null || $this->key = $key;
		$doubleArrow === null || $this->doubleArrow = $doubleArrow;
		$ampersand === null || $this->ampersand = $ampersand;
		$ellipsis === null || $this->ellipsis = $ellipsis;
		$this->value = $value;
	}
}
