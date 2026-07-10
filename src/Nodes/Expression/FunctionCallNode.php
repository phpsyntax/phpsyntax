<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\AccessKind;
use PhpSyntax\Nodes\{ArgumentListNode, ExpressionNode, NameNode};


/**
 * Function call by name or on an expression.
 */
final class FunctionCallNode extends ExpressionNode
{
	public const Slots = ['name', 'arguments'];


	/** @internal */
	public function __construct(
		public NameNode|ExpressionNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ArgumentListNode $arguments { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/** A call of the name, or of the expression, in parentheses where the call would take it for something else. */
	public static function of(NameNode|ExpressionNode $name, ?ArgumentListNode $arguments = null): self
	{
		self::checkDetached($name, $arguments);
		return new self(
			$name instanceof ExpressionNode && !$name->isDereferenceable(AccessKind::Call)
				? ParenthesizedNode::of($name)
				: $name->setEdgeTrivia([], []),
			$arguments?->setEdgeTrivia([], []) ?? ArgumentListNode::of(),
		);
	}
}
