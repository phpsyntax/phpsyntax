<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;


/**
 * Operator written with no left operand, whose right one reaches as far as the code lets it:
 * `fn() => …`, `throw`, `print`, `yield`, `yield from` and `include`. It can capture nothing before
 * itself, so where nothing follows it, it stands bare however loosely it binds, and everywhere else
 * it needs parentheses.
 */
interface RightExtendingNode extends OperatorNode
{
}
