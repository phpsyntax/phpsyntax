<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Analyses;

use PhpSyntax\{Node, Token, TokenKind};
use PhpSyntax\Nodes\{AnonymousClassNode, ClassLikeNode, FunctionLikeNode};
use PhpSyntax\Nodes\Expression\{ArrowFunctionNode, ClosureNode};
use PhpSyntax\Nodes\Member\{MethodNode, PropertyHookNode};
use PhpSyntax\Nodes\Statement\FunctionNode;


/**
 * Where a node stands: the enclosing function-like construct and class, whether `$this` is available,
 * and what a closure captures.
 */
final class Scope
{
	/**
	 * The innermost function, method, closure, arrow function or property hook around the node.
	 */
	public function getFunction(Node|Token $node): (FunctionLikeNode&Node)|null
	{
		return self::findEnclosing($node, FunctionLikeNode::class);
	}


	public function getClass(Node|Token $node): (ClassLikeNode&Node)|null
	{
		return self::findEnclosing($node, ClassLikeNode::class);
	}


	/**
	 * The innermost construct of the class the node stands in, itself excluded; a token stands in the node
	 * it belongs to.
	 * @template T of object
	 * @param  class-string<T>  $class
	 * @return (T&Node)|null
	 */
	private static function findEnclosing(Node|Token $node, string $class): ?Node
	{
		for ($ancestor = $node->parent; $ancestor; $ancestor = $ancestor->parent) {
			if ($ancestor instanceof $class) {
				return $ancestor;
			}
		}

		return null;
	}


	/**
	 * Whether `$this` refers to an object here: inside a non-static method or hook of a class, also through
	 * non-static closures and arrow functions nested in it.
	 */
	public function hasThis(Node|Token $node): bool
	{
		for ($ancestor = $node->parent; $ancestor; $node = $ancestor, $ancestor = $ancestor->parent) {
			if ($ancestor instanceof ClosureNode || $ancestor instanceof ArrowFunctionNode) {
				if ($ancestor->staticKeyword) {
					return false;
				}
			} elseif ($ancestor instanceof MethodNode) {
				return !$ancestor->modifiers->has(TokenKind::Static) && $this->getClass($ancestor) !== null;
			} elseif ($ancestor instanceof PropertyHookNode) {
				return true;
			} elseif ($ancestor instanceof AnonymousClassNode && $node === $ancestor->arguments) {
				continue; // the arguments of new class(...) are evaluated outside the class
			} elseif ($ancestor instanceof FunctionNode || $ancestor instanceof ClassLikeNode) {
				return false;
			}
		}

		return false;
	}


	/**
	 * Names of the variables a closure captures with `use (...)`, with the dollar sign.
	 * @return list<string>
	 */
	public function getCapturedVariables(ClosureNode $closure): array
	{
		$names = [];
		foreach ($closure->uses?->variables->getItems() ?? [] as $use) {
			if ($use->variable->name instanceof Token) {
				$names[] = $use->variable->name->text;
			}
		}

		return $names;
	}
}
