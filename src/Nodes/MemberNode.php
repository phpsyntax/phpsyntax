<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;


/**
 * Member of a class-like declaration: property, constant, method, trait use, enum case.
 */
abstract class MemberNode extends Node
{
}
