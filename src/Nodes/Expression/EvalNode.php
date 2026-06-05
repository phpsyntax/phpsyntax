<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * `eval(...)`.
 */
final class EvalNode extends ExpressionNode
{
	public const Slots = ['evalKeyword', 'openParen', 'expression', 'closeParen'];

	public Token $evalKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }


	/** @internal */
	public function __construct(Token $evalKeyword, Token $openParen, ExpressionNode $expression, Token $closeParen)
	{
		$this->evalKeyword = $evalKeyword;
		$this->openParen = $openParen;
		$this->expression = $expression;
		$this->closeParen = $closeParen;
	}
}
