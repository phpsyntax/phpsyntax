<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Helpers, Lexer, Token};
use PhpSyntax\Nodes\{ExpressionNode, OperatorNode};


/**
 * Combined assignment `$a += $b`, whose operator token tells which operation is baked into it.
 */
final class CombinedAssignmentNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['target', 'operator', 'expression'];

	private const Operators = ['+=', '-=', '*=', '/=', '.=', '%=', '**=', '&=', '|=', '^=', '<<=', '>>=', '??='];

	public ExpressionNode $target { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); }

	public int $precedence { get => 90; }
	public Associativity $associativity { get => Associativity::Right; }


	/** @internal */
	public function __construct(ExpressionNode $target, Token $operator, ExpressionNode $expression)
	{
		$this->target = $target;
		$this->operator = $operator;
		$this->expression = $expression;
	}


	/**
	 * Replaces the operator by another combined assignment one, the trivia around it staying; every one binds alike,
	 * so no parentheses change.
	 * @throws \InvalidArgumentException  for what is no combined assignment operator
	 */
	public function replaceOperator(string $operator): void
	{
		if (!in_array($operator, self::Operators, true)) {
			throw new \InvalidArgumentException(Helpers::formatCode($operator) . ' is not a combined assignment operator.');
		} elseif ($operator !== $this->operator->text) {
			$this->operator->replaceWith(Lexer::readToken($operator) ?? throw new \LogicException);
		}
	}
}
