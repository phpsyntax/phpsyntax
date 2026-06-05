<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\{Associativity, Token};
use PhpSyntax\Nodes\{ExpressionNode, OperatorNode};


/**
 * Ternary conditional `$a ? $b : $c`, or the elvis form `$a ?: $c` leaving `then` empty.
 */
final class TernaryNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['condition', 'question', 'then', 'colon', 'else'];

	public ExpressionNode $condition { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $question { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ExpressionNode $then = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $else { set => $this->prepareSlot(__PROPERTY__, $value); }

	public int $precedence { get => 100; }
	public Associativity $associativity { get => Associativity::None; }


	/** @internal */
	public function __construct(ExpressionNode $condition, Token $question, ?ExpressionNode $then, Token $colon, ExpressionNode $else)
	{
		$this->condition = $condition;
		$this->question = $question;
		$then === null || $this->then = $then;
		$this->colon = $colon;
		$this->else = $else;
	}
}
