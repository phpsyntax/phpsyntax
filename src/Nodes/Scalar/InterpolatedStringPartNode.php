<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Scalar;

use PhpSyntax\Node;
use PhpSyntax\Token;


/**
 * Literal text between interpolations, whitespace included. What its escape sequences mean depends on the
 * string it stands in, so the string is what resolves them, with PhpSyntax\Escaping.
 */
final class InterpolatedStringPartNode extends Node
{
	public const Slots = ['token'];


	/** @internal */
	public function __construct(
		public Token $token { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
