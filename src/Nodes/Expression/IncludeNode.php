<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Token};
use PhpSyntax\Nodes\{ExpressionNode, RightExtendingNode};


/**
 * `include`, `include_once`, `require` or `require_once`; the keyword token tells which.
 */
final class IncludeNode extends ExpressionNode implements RightExtendingNode
{
	public const Slots = ['includeKeyword', 'expression'];

	public Token $includeKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); }

	public int $precedence { get => 20; }
	public Associativity $associativity { get => Associativity::Left; }


	/** @internal */
	public function __construct(Token $includeKeyword, ExpressionNode $expression)
	{
		$this->includeKeyword = $includeKeyword;
		$this->expression = $expression;
	}
}
