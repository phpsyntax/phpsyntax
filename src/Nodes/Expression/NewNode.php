<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\AnonymousClassNode;
use PhpSyntax\Nodes\ArgumentListNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\OperatorNode;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;
use PhpSyntax\Trivia;
use PhpSyntax\TriviaKind;


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
		$class->setEdgeTrivia([], []);
		if ($class instanceof ExpressionNode && !$class->canNameClass()) {
			$class = ParenthesizedNode::of($class);
		}

		$arguments?->setEdgeTrivia([], []);
		$keyword = new Token(TokenKind::New, 'new');
		$keyword->setTrailingTrivia([new Trivia(TriviaKind::Whitespace, ' ')]);
		return new self($keyword, $class, $arguments);
	}


	public function getPrecedence(): array
	{
		return [270, self::NonAssociative];
	}
}
