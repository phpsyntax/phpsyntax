<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;


/**
 * Literal written in the source: a number, a string in any of its delimiters, an interpolated string,
 * a boolean, null and a magic constant.
 */
abstract class ScalarNode extends ExpressionNode
{
}
