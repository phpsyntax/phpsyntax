<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Scalar;

use PhpSyntax\Nodes\ScalarNode;
use PhpSyntax\Token;


/**
 * Boolean literal, kept in the letter case and with the leading backslash it is written with: `true`, `FALSE`, `\True`.
 */
final class BooleanNode extends ScalarNode
{
	public const Slots = ['token'];

	public bool $value {
		get => strcasecmp(ltrim($this->token->text, '\\'), 'true') === 0;
	}


	/** @internal */
	public function __construct(
		public Token $token { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
