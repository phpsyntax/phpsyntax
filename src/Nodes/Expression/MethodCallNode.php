<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ArgumentListNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\IdentifierNode;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;


/**
 * Method call with -> or ?->.
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
		$object->setEdgeTrivia([], []);
		$arguments?->setEdgeTrivia([], []);
		return new self(
			object: $object->isDereferenceable() ? $object : ParenthesizedNode::of($object),
			operator: $nullsafe ? new Token(TokenKind::NullsafeObjectOperator, '?->') : new Token(TokenKind::ObjectOperator, '->'),
			openBrace: null,
			name: IdentifierNode::fromText($name),
			closeBrace: null,
			arguments: $arguments ?? ArgumentListNode::of(),
		);
	}


	/** Whether the call is written with ?->, which skips it when the object is null. */
	public function isNullsafe(): bool
	{
		return $this->operator->kind === TokenKind::NullsafeObjectOperator;
	}
}
