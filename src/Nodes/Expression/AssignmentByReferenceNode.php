<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Token};
use PhpSyntax\Nodes\{ExpressionNode, OperatorNode};


/**
 * Assignment by reference `$a = &$b`; the grammar takes a variable or a `new` expression on the right.
 */
final class AssignmentByReferenceNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['target', 'equals', 'ampersand', 'expression'];

	public ExpressionNode $target { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $equals { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $ampersand { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); }

	public int $precedence { get => 90; }
	public Associativity $associativity { get => Associativity::Right; }


	/** @internal */
	public function __construct(ExpressionNode $target, Token $equals, Token $ampersand, ExpressionNode $expression)
	{
		$this->target = $target;
		$this->equals = $equals;
		$this->ampersand = $ampersand;
		$this->expression = $expression;
	}
}
