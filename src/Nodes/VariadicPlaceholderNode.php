<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Token;


/**
 * The ... placeholder of a first-class callable: f(...).
 */
final class VariadicPlaceholderNode extends Node
{
	public const Slots = ['ellipsis'];


	/** @internal */
	public function __construct(
		public Token $ellipsis { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
