<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * Array or string offset access: `$a[$i]`, `$a[]`.
 */
final class ArrayAccessNode extends ExpressionNode
{
	public const Slots = ['expression', 'openBracket', 'index', 'closeBracket'];

	public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openBracket { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ExpressionNode $index = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeBracket { set => $this->prepareSlot(__PROPERTY__, $value); }


	/** @internal */
	public function __construct(ExpressionNode $expression, Token $openBracket, ?ExpressionNode $index, Token $closeBracket)
	{
		$this->expression = $expression;
		$this->openBracket = $openBracket;
		$index === null || $this->index = $index;
		$this->closeBracket = $closeBracket;
	}
}
