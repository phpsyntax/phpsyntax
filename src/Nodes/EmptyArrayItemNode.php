<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;


/**
 * Skipped item of a destructuring list ([, $b] = $x); has no tokens.
 */
final class EmptyArrayItemNode extends Node
{
	public const Slots = [];


	/** @internal */
	public function __construct()
	{
	}
}
