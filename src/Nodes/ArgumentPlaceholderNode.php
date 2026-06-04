<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * The `?` placeholder of a partial function application, optionally named: `f(?)`, `f(name: ?)`.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class ArgumentPlaceholderNode extends Node
{
	public const Slots = ['name', 'colon', 'question'];

	public ?IdentifierNode $name = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $colon = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $question { set => $this->prepareSlot(__PROPERTY__, $value); }


	/** @internal */
	public function __construct(?IdentifierNode $name, ?Token $colon, Token $question)
	{
		$name === null || $this->name = $name;
		$colon === null || $this->colon = $colon;
		$this->question = $question;
	}
}
