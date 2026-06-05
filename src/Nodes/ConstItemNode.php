<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * Constant of a `const` statement or a class constant declaration.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class ConstItemNode extends Node
{
	public const Slots = ['name', 'equals', 'value'];

	public IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $equals { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $value { set => $this->prepareSlot(__PROPERTY__, $value); }


	/** @internal */
	public function __construct(IdentifierNode $name, Token $equals, ExpressionNode $value)
	{
		$this->name = $name;
		$this->equals = $equals;
		$this->value = $value;
	}
}
