<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;


abstract class TypeNode extends Node
{
	/** Whether the type accepts null: ?T, a union with null, and mixed. */
	public function allowsNull(): bool
	{
		if ($this instanceof Type\NullableTypeNode) {
			return true;
		} elseif ($this instanceof Type\NamedTypeNode) {
			return $this->name->equals('null') || $this->name->equals('mixed');
		} elseif ($this instanceof Type\UnionTypeNode) {
			foreach ($this->types->getItems() as $member) {
				if ($member->allowsNull()) {
					return true;
				}
			}
		}

		return false;
	}
}
