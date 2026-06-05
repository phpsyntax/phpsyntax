<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ArgumentListNode, ExpressionNode};
use PhpSyntax\Token;


/**
 * `exit` or `die` with optional arguments.
 */
final class ExitNode extends ExpressionNode
{
	public const Slots = ['exitKeyword', 'arguments'];

	public Token $exitKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ArgumentListNode $arguments = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/** @internal */
	public function __construct(Token $exitKeyword, ?ArgumentListNode $arguments)
	{
		$this->exitKeyword = $exitKeyword;
		$arguments === null || $this->arguments = $arguments;
	}
}
