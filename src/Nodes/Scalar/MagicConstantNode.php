<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Scalar;

use PhpSyntax\Nodes\ScalarNode;
use PhpSyntax\Token;


/**
 * Magic constant: __LINE__, __FILE__, __DIR__, __CLASS__, __TRAIT__, __METHOD__, __FUNCTION__, __PROPERTY__, __NAMESPACE__.
 */
final class MagicConstantNode extends ScalarNode
{
	public const Slots = ['token'];


	/** @internal */
	public function __construct(
		public Token $token { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
