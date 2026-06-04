<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Scalar;

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\ExpressionNode;


/**
 * Braced interpolation inside a string: `{$expr}`, `${name}`, `${name[expr]}` or `${expr}`.
 */
final class InterpolationNode extends Node
{
	public const Slots = ['openBrace', 'expression', 'closeBrace'];


	/** @internal */
	public function __construct(
		public Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
