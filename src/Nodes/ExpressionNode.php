<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;


/**
 * Expression, which stands for a value; a destructuring stands where a target is written and is a ListNode,
 * not one of these.
 */
abstract class ExpressionNode extends Node
{
}
