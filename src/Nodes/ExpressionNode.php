<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * Expression, which stands for a value; a destructuring stands where a target is written and is a DestructuringNode,
 * not one of these.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
abstract class ExpressionNode extends Node
{
}
