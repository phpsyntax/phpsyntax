<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;


/**
 * Operator written with no left operand, whose right one reaches as far as the code lets it:
 * `fn() => …`, `throw`, `print`, `yield`, `yield from` and `include`. It can capture nothing before
 * itself, so it stands bare however loosely it binds, unless an operator follows it, which its operand
 * would take in; before one it needs parentheses.
 */
interface RightExtendingNode extends OperatorNode
{
}
