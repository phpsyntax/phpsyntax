<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;

use PhpSyntax\Nodes\{ExpressionNode, NameNode, StatementNode, TypeNode};


/**
 * Builds nodes out of text: everything but a whole file, which `Parser::parse()` reads.
 */
final class Builder
{
	public function __construct(
		private readonly Parser $parser = new Parser,
	) {
	}


	/**
	 * The expression the code is read as.
	 * @throws ParseException
	 */
	public function expression(string $code): ExpressionNode
	{
		return $this->fragment(ExpressionNode::class, $code);
	}


	/**
	 * The statement the code is read as.
	 * @throws ParseException
	 */
	public function statement(string $code): StatementNode
	{
		return $this->fragment(StatementNode::class, $code);
	}


	/** @throws ParseException */
	public function type(string $code): TypeNode
	{
		return $this->fragment(TypeNode::class, $code);
	}


	/** @throws ParseException */
	public function name(string $code): NameNode
	{
		return $this->fragment(NameNode::class, $code);
	}


	/**
	 * The node of the class the code is read as, parsed inside the code such a node stands in: an expression, a
	 * statement, a type, a name, a member, a parameter, an argument, an array item, an import item and the other
	 * kinds of items, or a class deriving from one of them. It comes back detached, without original positions and
	 * with empty trivia on its edges, and code that leaves something over is refused.
	 * @template T of Node
	 * @param  class-string<T>  $class
	 * @return T
	 * @throws ParseException
	 */
	public function fragment(string $class, string $code): Node
	{
		return $this->parser->parseFragment($class, $code);
	}
}
