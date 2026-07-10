<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{AccessKind, Token, TokenKind};
use PhpSyntax\Nodes\{ArgumentListNode, ExpressionNode, IdentifierNode, NameNode};


/**
 * Static method call: `A::b()`, `$a::b()`, `A::{expr}()`.
 */
final class StaticMethodCallNode extends ExpressionNode
{
	public const Slots = ['class', 'doubleColon', 'openBrace', 'name', 'closeBrace', 'arguments'];


	/** @internal */
	public function __construct(
		public NameNode|ExpressionNode $class { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $doubleColon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public IdentifierNode|ExpressionNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ArgumentListNode $arguments { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/** A call of the static method of the class, an expression in parentheses where `::` could not follow it bare. */
	public static function of(NameNode|ExpressionNode $class, string $name, ?ArgumentListNode $arguments = null): self
	{
		$identifier = IdentifierNode::fromText($name);
		self::checkDetached($class, $arguments);
		return new self(
			class: $class instanceof ExpressionNode && !$class->isDereferenceable(AccessKind::ClassName)
				? ParenthesizedNode::of($class)
				: $class->setEdgeTrivia([], []),
			doubleColon: new Token(TokenKind::DoubleColon, '::'),
			openBrace: null,
			name: $identifier,
			closeBrace: null,
			arguments: $arguments?->setEdgeTrivia([], []) ?? ArgumentListNode::of(),
		);
	}
}
