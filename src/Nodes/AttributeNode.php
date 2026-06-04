<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * Attribute with optional arguments.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class AttributeNode extends Node
{
	public const Slots = ['name', 'arguments'];

	public NameNode $name { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ArgumentListNode $arguments = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/** @internal */
	public function __construct(NameNode $name, ?ArgumentListNode $arguments)
	{
		$this->name = $name;
		$arguments === null || $this->arguments = $arguments;
	}
}
