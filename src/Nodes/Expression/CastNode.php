<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Helpers, Lexer, Token};
use PhpSyntax\Nodes\{ExpressionNode, OperatorNode};


/**
 * Type cast; the cast token keeps its spelling including inner whitespace: ( int ).
 */
final class CastNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['operator', 'expression'];

	public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** The type the cast converts to, in the name PHP knows it by: (integer) is int, (double) and (real) are float. */
	public string $typeName {
		get {
			$name = strtolower(trim($this->operator->text, "() \t\r\n"));
			return match ($name) {
				'integer' => 'int',
				'boolean' => 'bool',
				'double', 'real' => 'float',
				'binary' => 'string',
				default => $name,
			};
		}
	}

	public int $precedence { get => 240; }
	public Associativity $associativity { get => Associativity::Right; }


	/** @internal */
	public function __construct(Token $operator, ExpressionNode $expression)
	{
		$this->operator = $operator;
		$this->expression = $expression;
	}


	/**
	 * Replaces the cast by another one, `(int)` by `(string)` for instance, the trivia around it staying; every cast
	 * binds alike, so no parentheses change. Another spelling of the same cast is `setText()` of the operator.
	 * @throws \InvalidArgumentException  for what is no cast
	 */
	public function replaceOperator(string $operator): void
	{
		$token = Lexer::readToken($operator);
		if (!$token?->is([
			Token::IntCast, Token::FloatCast, Token::StringCast, Token::ArrayCast, Token::ObjectCast, Token::BoolCast, Token::UnsetCast,
			Token::VoidCast,
		])) {
			throw new \InvalidArgumentException(Helpers::formatCode($operator) . ' is not a cast.');
		} elseif ($operator !== $this->operator->text) {
			$this->operator->replaceWith($token);
		}
	}
}
