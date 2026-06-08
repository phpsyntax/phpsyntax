<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\Token;


/**
 * Function written as an expression, without a name: closure, arrow function; an interface, because the two
 * share no base class but the expression.
 */
interface AnonymousFunctionNode extends FunctionLikeNode
{
	public ?Token $staticKeyword { get; }
}
