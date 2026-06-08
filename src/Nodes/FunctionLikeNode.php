<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;


/**
 * Declaration with parameters and a body: function, method, closure, arrow function, property hook;
 * an interface, because a closure and an arrow function are expressions and the rest are not.
 */
interface FunctionLikeNode
{
	/** @var ?SeparatedNodeList<ParameterNode>  null for a property hook written without parentheses */
	public ?SeparatedNodeList $parameters { get; }

	/** The return type declared; null where none is, a property hook having none to declare. */
	public ?TypeNode $returnType { get; }
}
