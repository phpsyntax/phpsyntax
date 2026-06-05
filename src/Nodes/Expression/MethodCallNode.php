<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ArgumentListNode, ExpressionNode, IdentifierNode};
use PhpSyntax\{Token, TokenKind};


/**
 * Method call with `->` or `?->`.
 */
final class MethodCallNode extends ExpressionNode
{
	public const Slots = ['object', 'operator', 'openBrace', 'name', 'closeBrace', 'arguments'];


	/** @internal */
	public function __construct(
		public ExpressionNode $object { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public IdentifierNode|ExpressionNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ArgumentListNode $arguments { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/** Whether the call is written with `?->`, which skips it when the object is null. */
	public function isNullsafe(): bool
	{
		return $this->operator->kind === TokenKind::NullsafeObjectOperator;
	}
}
