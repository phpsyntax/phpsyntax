<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\AccessKind;
use PhpSyntax\Nodes\ArgumentListNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\IdentifierNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;


/**
 * Static method call: A::b(), $a::b(), A::{expr}().
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


	/** A call of the static method of the class, an expression in parentheses where :: could not follow it bare. */
	public static function of(NameNode|ExpressionNode $class, string $name, ?ArgumentListNode $arguments = null): self
	{
		$class->setEdgeTrivia([], []);
		if ($class instanceof ExpressionNode && !$class->isDereferenceable(AccessKind::ClassName)) {
			$class = ParenthesizedNode::of($class);
		}

		$arguments?->setEdgeTrivia([], []);
		return new self(
			class: $class,
			doubleColon: new Token(TokenKind::DoubleColon, '::'),
			openBrace: null,
			name: IdentifierNode::fromText($name),
			closeBrace: null,
			arguments: $arguments ?? ArgumentListNode::of(),
		);
	}
}
