<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{AnonymousClassNode, ArgumentListNode, ExpressionNode, NameNode, OperatorNode};
use PhpSyntax\Token;


/**
 * Instantiation of a named, dynamic or anonymous class.
 */
final class NewNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['newKeyword', 'class', 'arguments'];


	/** @internal */
	public function __construct(
		public Token $newKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public NameNode|ExpressionNode|AnonymousClassNode $class { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ArgumentListNode $arguments { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	public function getPrecedence(): array
	{
		return [270, self::NonAssociative];
	}
}
