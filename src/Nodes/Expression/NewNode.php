<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{AnonymousClassNode, ArgumentListNode, ExpressionNode, NameNode, OperatorNode};
use PhpSyntax\{Token, TokenKind, Trivia, TriviaKind};


/**
 * Instantiation of a named, dynamic or anonymous class.
 */
final class NewNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['newKeyword', 'class', 'arguments'];


	/** @internal */
	public function __construct(
		public Token $newKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public NameNode|ExpressionNode|AnonymousClassNode $class { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ArgumentListNode $arguments { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/** An instantiation of the class, an expression in parentheses where it could not name one bare; no list is written where none is given. */
	public static function of(NameNode|ExpressionNode $class, ?ArgumentListNode $arguments = null): self
	{
		self::checkDetached($class, $arguments);
		return new self(
			new Token(TokenKind::New, 'new')->setTrailingTrivia([new Trivia(TriviaKind::Whitespace, ' ')]),
			$class instanceof ExpressionNode && !$class->canNameClass() ? ParenthesizedNode::of($class) : $class->setEdgeTrivia([], []),
			$arguments?->setEdgeTrivia([], []),
		);
	}


	public function getPrecedence(): array
	{
		return [270, self::NonAssociative];
	}
}
