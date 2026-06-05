<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Token};
use PhpSyntax\Nodes\{ExpressionNode, RightExtendingNode};


/**
 * `yield`, `yield $value` or `yield $key => $value`.
 */
final class YieldNode extends ExpressionNode implements RightExtendingNode
{
	public const Slots = ['yieldKeyword', 'key', 'doubleArrow', 'value'];

	public Token $yieldKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ExpressionNode $key = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $doubleArrow = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ExpressionNode $value = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	public int $precedence { get => 65; }
	public Associativity $associativity { get => Associativity::Right; }


	/** @internal */
	public function __construct(Token $yieldKeyword, ?ExpressionNode $key, ?Token $doubleArrow, ?ExpressionNode $value)
	{
		$this->yieldKeyword = $yieldKeyword;
		$key === null || $this->key = $key;
		$doubleArrow === null || $this->doubleArrow = $doubleArrow;
		$value === null || $this->value = $value;
	}
}
