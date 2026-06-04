<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * The `?` placeholder of a partial function application, optionally named: `f(?)`, `f(name: ?)`.
 */
final class ArgumentPlaceholderNode extends Node
{
	public const Slots = ['name', 'colon', 'question'];


	/** @internal */
	public function __construct(
		public ?IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $question { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
