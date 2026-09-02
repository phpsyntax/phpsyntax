<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\Token;


/**
 * Declaration with parameters and a body: function, method, closure, arrow function, property hook;
 * an interface, because a closure and an arrow function are expressions and the rest are not.
 */
interface FunctionLikeNode extends AttributeAwareNode
{
	/** The `&` by which the declaration returns a reference, that of `&get` among them; null where none is. */
	public ?Token $ampersand { get; }

	/** The parenthesis opening the parameters; null for a property hook written without parentheses. */
	public ?Token $openParen { get; }

	/** @var ?SeparatedNodeList<ParameterNode>  null for a property hook written without parentheses */
	public ?SeparatedNodeList $parameters { get; }

	/** The parenthesis closing the parameters; null for a property hook written without parentheses. */
	public ?Token $closeParen { get; }

	/** The return type declared; null where none is, a property hook having none to declare. */
	public ?TypeNode $returnType { get; }

	/**
	 * Writes the return type together with its colon, or removes both, their comments staying after the closing
	 * parenthesis; the gap before the body stays where it was, and a type standing in a tree comes as a copy without
	 * the trivia on its edges.
	 * @throws \LogicException  for a property hook, which has no return type
	 */
	function setReturnType(?TypeNode $type): static;
}
