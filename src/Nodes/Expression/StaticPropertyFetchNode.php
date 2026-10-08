<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Helpers, Token};
use PhpSyntax\Nodes\{ExpressionNode, NameNode};


/**
 * Static property access: `A::$b`, `A::$$b`, `A::${expr}`. The name is written the way a variable is, dollar
 * and braces included, but it names a property.
 */
final class StaticPropertyFetchNode extends ExpressionNode
{
	public const Slots = ['class', 'doubleColon', 'dollar', 'openBrace', 'name', 'closeBrace'];

	public NameNode|ExpressionNode $class { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $doubleColon { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $dollar = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $openBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token|ExpressionNode $name { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $closeBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** The name of the property without the dollar sign; null where the name is an expression (`$$b`, `${expr}`). */
	public ?string $plainName {
		get => $this->name instanceof Token ? substr($this->name->text, 1) : null;
	}


	/** @internal */
	public function __construct(
		NameNode|ExpressionNode $class,
		Token $doubleColon,
		?Token $dollar,
		?Token $openBrace,
		Token|ExpressionNode $name,
		?Token $closeBrace,
	) {
		$this->class = $class;
		$this->doubleColon = $doubleColon;
		$dollar === null || $this->dollar = $dollar;
		$openBrace === null || $this->openBrace = $openBrace;
		$this->name = $name;
		$closeBrace === null || $this->closeBrace = $closeBrace;
	}


	/**
	 * Writes the name of the property, without the dollar sign: a plain name changes its text, and a name given by an
	 * expression is replaced by a variable token, the dollar and the braces around the expression gone and the
	 * trivia on its edges kept, so that `$plainName` reads the new name.
	 */
	public function rename(string $name): static
	{
		Helpers::checkVariableName($name);
		if ($this->name instanceof Token) {
			$this->name->setText('$' . $name);
			return $this;
		}

		$token = new Token(Token::Variable, '$' . $name);
		self::takeEdgeTrivia($this->dollar ?? $this->openBrace ?? $this->name, $this->closeBrace ?? $this->name, $token);
		$this->dollar = $this->openBrace = $this->closeBrace = null;
		$this->name = $token;
		return $this;
	}
}
