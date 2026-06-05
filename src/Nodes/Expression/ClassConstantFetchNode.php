<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ExpressionNode, IdentifierNode, NameNode};
use PhpSyntax\Token;


/**
 * Class constant access: `A::B`, `A::class`, `A::{expr}`.
 */
final class ClassConstantFetchNode extends ExpressionNode
{
	public const Slots = ['class', 'doubleColon', 'openBrace', 'name', 'closeBrace'];

	public NameNode|ExpressionNode $class { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $doubleColon { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $openBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public IdentifierNode|ExpressionNode $name { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $closeBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** The name of the constant; null where the name is an expression (`A::{expr}`). */
	public ?string $plainName {
		get => $this->name instanceof IdentifierNode ? $this->name->text : null;
	}


	/** @internal */
	public function __construct(
		NameNode|ExpressionNode $class,
		Token $doubleColon,
		?Token $openBrace,
		IdentifierNode|ExpressionNode $name,
		?Token $closeBrace,
	) {
		$this->class = $class;
		$this->doubleColon = $doubleColon;
		$openBrace === null || $this->openBrace = $openBrace;
		$this->name = $name;
		$closeBrace === null || $this->closeBrace = $closeBrace;
	}
}
