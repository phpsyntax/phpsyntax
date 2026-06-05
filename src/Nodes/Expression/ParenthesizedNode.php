<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * Expression in parentheses.
 */
final class ParenthesizedNode extends ExpressionNode
{
	public const Slots = ['openParen', 'expression', 'closeParen'];

	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }


	/** @internal */
	public function __construct(Token $openParen, ExpressionNode $expression, Token $closeParen)
	{
		$this->openParen = $openParen;
		$this->expression = $expression;
		$this->closeParen = $closeParen;
	}
}
