<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\Expression\VariableNode;


/**
 * Variable of a `static` statement with an optional initializer.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class StaticVariableNode extends Node
{
	public const Slots = ['variable', 'equals', 'default'];

	public VariableNode $variable { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $equals = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ExpressionNode $default = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/** @internal */
	public function __construct(VariableNode $variable, ?Token $equals, ?ExpressionNode $default)
	{
		$this->variable = $variable;
		$equals === null || $this->equals = $equals;
		$default === null || $this->default = $default;
	}
}
