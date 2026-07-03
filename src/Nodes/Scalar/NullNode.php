<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Scalar;

use PhpSyntax\Nodes\ScalarNode;
use PhpSyntax\Token;


/**
 * Null literal, kept in the letter case and with the leading backslash it is written with: null, NULL, \Null.
 */
final class NullNode extends ScalarNode
{
	public const Slots = ['token'];


	/** @internal */
	public function __construct(
		public Token $token { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
