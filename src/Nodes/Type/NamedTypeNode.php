<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Type;

use PhpSyntax\Nodes\{NameNode, TypeNode};
use function in_array;


/**
 * Type given by a name: builtin (`int`, `static`, `array`, `callable`) or a class.
 */
final class NamedTypeNode extends TypeNode
{
	public const Slots = ['name'];


	/** @internal */
	public function __construct(
		public NameNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/** Whether the type is one PHP knows itself, `self`, `static` and `parent` among them. */
	public function isBuiltin(): bool
	{
		return in_array(strtolower($this->name->text), [
			'array', 'bool', 'callable', 'false', 'float', 'int', 'iterable', 'mixed', 'never',
			'null', 'object', 'parent', 'self', 'static', 'string', 'true', 'void',
		], true);
	}
}
