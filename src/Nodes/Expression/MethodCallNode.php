<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ArgumentListNode, ExpressionNode, IdentifierNode};
use PhpSyntax\{Token, TokenKind};


/**
 * Method call with `->` or `?->`.
 */
final class MethodCallNode extends ExpressionNode
{
	public const Slots = ['object', 'operator', 'openBrace', 'name', 'closeBrace', 'arguments'];


	/** @internal */
	public function __construct(
		public ExpressionNode $object { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public IdentifierNode|ExpressionNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ArgumentListNode $arguments { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/** A call of the method on the object, in parentheses where it could not be reached into bare. */
	public static function of(
		ExpressionNode $object,
		string $name,
		?ArgumentListNode $arguments = null,
		bool $nullsafe = false,
	): self
	{
		$identifier = IdentifierNode::fromText($name);
		self::checkDetached($object, $arguments);
		return new self(
			object: $object->isDereferenceable() ? $object->setEdgeTrivia([], []) : ParenthesizedNode::of($object),
			operator: $nullsafe ? new Token(TokenKind::NullsafeObjectOperator, '?->') : new Token(TokenKind::ObjectOperator, '->'),
			openBrace: null,
			name: $identifier,
			closeBrace: null,
			arguments: $arguments?->setEdgeTrivia([], []) ?? ArgumentListNode::of(),
		);
	}


	/** Whether the call is written with `?->`, which skips it when the object is null. */
	public function isNullsafe(): bool
	{
		return $this->operator->kind === TokenKind::NullsafeObjectOperator;
	}
}
