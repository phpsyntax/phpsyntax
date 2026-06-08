<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\IdentifierNode;


/**
 * What a trait use says about a method it takes from a trait: an alias or a precedence. The trait slot
 * is left to the two subclasses, an alias being able to leave it out where a precedence has to name it.
 */
abstract class TraitAdaptationNode extends Node
{
	public IdentifierNode $method;
	public Token $semicolon;
}
