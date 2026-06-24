<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;


/**
 * Declaration with members: class, interface, trait, enum, anonymous class; an interface, because an
 * anonymous class is an expression and the rest are statements.
 */
interface ClassLikeNode
{
	public ?IdentifierNode $name { get; }

	/** @var NodeList<MemberNode> */
	public NodeList $members { get; }
}
