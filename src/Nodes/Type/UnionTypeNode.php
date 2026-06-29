<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Type;

use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\TypeNode;


/**
 * Union type A|B; a member may be a parenthesized intersection (DNF).
 */
final class UnionTypeNode extends TypeNode
{
	public const Slots = ['types'];


	/** @internal */
	public function __construct(
		/** @var SeparatedNodeList<TypeNode> */
		public SeparatedNodeList $types { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
