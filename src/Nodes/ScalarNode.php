<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;


/**
 * Literal written in the source: a number, a string in any of its delimiters, an interpolated string,
 * a boolean, null and a magic constant. What it stands for is `hasValue()` and `toValue()`, which an
 * interpolating string and a magic constant answer no to, standing for what the code around them says.
 */
abstract class ScalarNode extends ExpressionNode
{
}
