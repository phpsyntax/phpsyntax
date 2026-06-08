<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Token};
use PhpSyntax\Nodes\{AnonymousClassNode, ArgumentListNode, ExpressionNode, NameNode, OperatorNode};


/**
 * Instantiation of a named, dynamic or anonymous class.
 */
final class NewNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['newKeyword', 'class', 'arguments'];

	public Token $newKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public NameNode|ExpressionNode|AnonymousClassNode $class { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ArgumentListNode $arguments = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	public int $precedence { get => 270; }
	public Associativity $associativity { get => Associativity::None; }


	/** @internal */
	public function __construct(Token $newKeyword, NameNode|ExpressionNode|AnonymousClassNode $class, ?ArgumentListNode $arguments)
	{
		$this->newKeyword = $newKeyword;
		$this->class = $class;
		$arguments === null || $this->arguments = $arguments;
	}
}
