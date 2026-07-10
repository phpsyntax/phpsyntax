<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Lexer\Lexer;
use PhpSyntax\Nodes\{ExpressionNode, OperatorNode};
use PhpSyntax\{Token, Trivia, TriviaKind};


/**
 * Combined assignment `$a += $b`, whose operator token tells which operation is baked into it.
 */
final class CombinedAssignmentNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['target', 'operator', 'expression'];

	private const Operators = ['+=', '-=', '*=', '/=', '.=', '%=', '**=', '&=', '|=', '^=', '<<=', '>>=', '??='];


	/** @internal */
	public function __construct(
		public ExpressionNode $target { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/**
	 * The combined assignment of the expression to the target, the expression in parentheses where it binds looser
	 * than the assignment takes.
	 * @throws \InvalidArgumentException  for what is no combined assignment operator, and for a target nothing can be assigned to
	 */
	public static function of(ExpressionNode $target, string $operator, ExpressionNode $expression): self
	{
		if (!in_array($operator, self::Operators, true)) {
			throw new \InvalidArgumentException("'$operator' is not a combined assignment operator.");
		} elseif (!$target->isWritable()) {
			throw new \InvalidArgumentException("'$target->text' is no place to assign to.");
		}

		self::checkDetached($target, $expression);
		static $lexer = new Lexer;
		$space = [new Trivia(TriviaKind::Whitespace, ' ')];
		$node = new self(
			$target,
			new Token($lexer->tokenize('<?php ' . $operator, withPositions: false)[0]->kind, $operator)->setTrailingTrivia($space),
			ParenthesizedNode::of($expression),
		);
		if ($node->expression instanceof ParenthesizedNode && $node->expression->isRedundant()) {
			$node->expression->replaceWith($node->expression->expression);
		}

		$target->setEdgeTrivia([], $space);
		return $node;
	}


	public function getPrecedence(): array
	{
		return [90, self::RightAssociative];
	}
}
