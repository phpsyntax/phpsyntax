<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Helpers, Token};
use PhpSyntax\Nodes\ExpressionNode;


/**
 * Variable: `$a`, `$$a`, `${expr}`; inside a string also a bare name in `${name}`.
 */
final class VariableNode extends ExpressionNode
{
	public const Slots = ['dollar', 'openBrace', 'name', 'closeBrace'];

	public ?Token $dollar = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $openBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token|ExpressionNode $name { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $closeBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** The name without the dollar sign; null where the name is an expression (`$$a`, `${expr}`). */
	public ?string $plainName {
		get {
			$name = $this->name;
			return $name instanceof Token
				? substr($name->text, $name->is(Token::Variable) ? 1 : 0) // a bare name in "${name}" carries none
				: null;
		}
	}


	/** @internal */
	public function __construct(?Token $dollar, ?Token $openBrace, Token|ExpressionNode $name, ?Token $closeBrace)
	{
		$dollar === null || $this->dollar = $dollar;
		$openBrace === null || $this->openBrace = $openBrace;
		$this->name = $name;
		$closeBrace === null || $this->closeBrace = $closeBrace;
	}


	/** Whether the variable is `$this`, the object a method runs on. */
	public function isThis(): bool
	{
		return $this->plainName === 'this';
	}


	/**
	 * Writes the name of the variable, without the dollar sign: a plain name changes its text, and a name given by an
	 * expression is replaced by a variable token, the dollar and the braces around the expression gone and the
	 * trivia on its edges kept, so that `$plainName` reads the new name.
	 */
	public function rename(string $name): static
	{
		Helpers::checkVariableName($name);
		if ($this->name instanceof Token) {
			$this->name->setText($this->name->is(Token::Variable) ? '$' . $name : $name);
			return $this;
		}

		$token = new Token(Token::Variable, '$' . $name);
		self::takeEdgeTrivia($this->dollar ?? $this->openBrace ?? $this->name, $this->closeBrace ?? $this->name, $token);
		$this->dollar = $this->openBrace = $this->closeBrace = null;
		$this->name = $token;
		return $this;
	}
}
