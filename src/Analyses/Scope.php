<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Analyses;

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\{AnonymousClassNode, AnonymousFunctionNode, ClassLikeNode};
use PhpSyntax\Nodes\Member\{MethodNode, PropertyHookNode};
use PhpSyntax\Nodes\Statement\FunctionNode;


/**
 * Whether `$this` is available where a node stands; the enclosing function-like construct or class is
 * `findAncestor(FunctionLikeNode::class)` or `findAncestor(ClassLikeNode::class)` of the node or token.
 */
final class Scope
{
	/**
	 * Whether `$this` refers to an object here: inside a non-static method or hook of a class, also through
	 * non-static closures and arrow functions nested in it.
	 */
	public function hasThis(Node|Token $node): bool
	{
		for ($ancestor = $node->parent; $ancestor; $node = $ancestor, $ancestor = $ancestor->parent) {
			if ($ancestor instanceof AnonymousFunctionNode) {
				if ($ancestor->staticKeyword) {
					return false;
				}
			} elseif ($ancestor instanceof MethodNode) {
				return !$ancestor->modifiers->has(Token::Static) && $ancestor->findAncestor(ClassLikeNode::class) !== null;
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
}
