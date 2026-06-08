<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\ExpressionNode;


/**
 * One property of a declaration with an optional default.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class PropertyItemNode extends Node
{
	public const Slots = ['name', 'equals', 'default'];

	public Token $name { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $equals = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ExpressionNode $default = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** The name of the property without the dollar sign. */
	public string $plainName {
		get => substr($this->name->text, 1);
	}


	/** @internal */
	public function __construct(Token $name, ?Token $equals, ?ExpressionNode $default)
	{
		$this->name = $name;
		$equals === null || $this->equals = $equals;
		$default === null || $this->default = $default;
	}
}
