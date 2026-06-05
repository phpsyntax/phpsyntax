<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ArgumentListNode, ExpressionNode, IdentifierNode};
use PhpSyntax\Token;


/**
 * Method call with `->` or `?->`.
 */
final class MethodCallNode extends ExpressionNode
{
	public const Slots = ['object', 'operator', 'openBrace', 'name', 'closeBrace', 'arguments'];

	public ExpressionNode $object { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $openBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public IdentifierNode|ExpressionNode $name { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $closeBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ArgumentListNode $arguments { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** The name of the method; null where the name is a variable or an expression (`$a->$b()`, `$a->{expr}()`). */
	public ?string $plainName {
		get => $this->name instanceof IdentifierNode ? $this->name->text : null;
	}

	/** Whether the call is written with `?->`, which skips it when the object is null. */
	public bool $nullsafe {
		get => $this->operator->is(Token::NullsafeObjectOperator);
	}


	/** @internal */
	public function __construct(
		ExpressionNode $object,
		Token $operator,
		?Token $openBrace,
		IdentifierNode|ExpressionNode $name,
		?Token $closeBrace,
		ArgumentListNode $arguments,
	) {
		$this->object = $object;
		$this->operator = $operator;
		$openBrace === null || $this->openBrace = $openBrace;
		$this->name = $name;
		$closeBrace === null || $this->closeBrace = $closeBrace;
		$this->arguments = $arguments;
	}


	/**
	 * Whether the method is one of `$this`, with either operator.
	 * @phpstan-assert-if-true VariableNode $this->object
	 */
	public function isOfThis(): bool
	{
		return $this->object instanceof VariableNode && $this->object->isThis();
	}
}
