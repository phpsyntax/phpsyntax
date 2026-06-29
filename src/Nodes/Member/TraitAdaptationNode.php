<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\Node;
use PhpSyntax\Nodes\IdentifierNode;
use PhpSyntax\Token;


/**
 * What a use statement says about a method it takes from a trait: an alias or a precedence. The trait
 * is not declared here, an alias being able to leave it out where a precedence has to name it.
 */
abstract class TraitAdaptationNode extends Node
{
	public IdentifierNode $method;
	public Token $semicolon;
}
