<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * Argument of a call: optionally named, by reference or unpacked.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class ArgumentNode extends Node
{
	public const Slots = ['name', 'colon', 'ampersand', 'ellipsis', 'value'];

	public ?IdentifierNode $name = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $colon = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $ampersand = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $ellipsis = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $value { set => $this->prepareSlot(__PROPERTY__, $value); }


	/** @internal */
	public function __construct(?IdentifierNode $name, ?Token $colon, ?Token $ampersand, ?Token $ellipsis, ExpressionNode $value)
	{
		$name === null || $this->name = $name;
		$colon === null || $this->colon = $colon;
		$ampersand === null || $this->ampersand = $ampersand;
		$ellipsis === null || $this->ellipsis = $ellipsis;
		$this->value = $value;
	}
}
