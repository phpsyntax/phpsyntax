<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ExpressionNode, IdentifierNode};
use PhpSyntax\{Token, TokenKind};


/**
 * Property access with `->` or `?->`; the name may be an identifier, a variable or a braced expression.
 */
final class PropertyFetchNode extends ExpressionNode
{
	public const Slots = ['object', 'operator', 'openBrace', 'name', 'closeBrace'];

	/** The name of the property; null where the name is a variable or an expression (`$a->$b`, `$a->{expr}`). */
	public ?string $plainName {
		get => $this->name instanceof IdentifierNode ? $this->name->text : null;
	}


	/** @internal */
	public function __construct(
		public ExpressionNode $object { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public IdentifierNode|ExpressionNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/** Whether the fetch is written with `?->`, which skips it when the object is null. */
	public function isNullsafe(): bool
	{
		return $this->operator->kind === TokenKind::NullsafeObjectOperator;
	}


	/** Whether the property is one of `$this`, with either operator. */
	public function isOfThis(): bool
	{
		return $this->object instanceof VariableNode && $this->object->isThis();
	}
}
