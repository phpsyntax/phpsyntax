<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ArgumentListNode, ExpressionNode, IdentifierNode, NameNode};
use PhpSyntax\Token;


/**
 * Static method call: `A::b()`, `$a::b()`, `A::{expr}()`.
 */
final class StaticMethodCallNode extends ExpressionNode
{
	public const Slots = ['class', 'doubleColon', 'openBrace', 'name', 'closeBrace', 'arguments'];


	/** @internal */
	public function __construct(
		public NameNode|ExpressionNode $class { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $doubleColon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public IdentifierNode|ExpressionNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ArgumentListNode $arguments { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
