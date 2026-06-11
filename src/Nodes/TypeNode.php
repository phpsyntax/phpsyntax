<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;


/**
 * Type written in a declaration: a name, a nullable, a union or an intersection.
 */
abstract class TypeNode extends Node
{
	/** Whether the type accepts null: `?T`, a union with `null`, and `mixed`. */
	public function allowsNull(): bool
	{
		if ($this instanceof Type\NullableTypeNode) {
			return true;
		} elseif ($this instanceof Type\NamedTypeNode) {
			return $this->name->equals('null') || $this->name->equals('mixed');
		} elseif ($this instanceof Type\UnionTypeNode) {
			return array_any($this->types->getItems(), fn(TypeNode $member) => $member->allowsNull());
		}

		return false;
	}
}
