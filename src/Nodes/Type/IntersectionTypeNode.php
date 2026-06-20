<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Type;

use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\TypeNode;
use PhpSyntax\Token;


/**
 * Intersection type A&B, parenthesized inside a union.
 */
final class IntersectionTypeNode extends TypeNode
{
	public const Slots = ['openParen', 'types', 'closeParen'];


	/** @internal */
	public function __construct(
		public ?Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<TypeNode> */
		public SeparatedNodeList $types { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
