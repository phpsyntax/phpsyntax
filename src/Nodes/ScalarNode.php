<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;


/**
 * Literal written in the source: a number, a string in any of its delimiters, an interpolated string,
 * a boolean, null and a magic constant.
 */
abstract class ScalarNode extends ExpressionNode
{
}
