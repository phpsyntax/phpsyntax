<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\Expression\VariableNode;


/**
 * Variable captured by a closure, optionally by reference.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class ClosureUseNode extends Node
{
	public const Slots = ['ampersand', 'variable'];

	public ?Token $ampersand = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public VariableNode $variable { set => $this->prepareSlot(__PROPERTY__, $value); }


	/** @internal */
	public function __construct(?Token $ampersand, VariableNode $variable)
	{
		$ampersand === null || $this->ampersand = $ampersand;
		$this->variable = $variable;
	}
}
