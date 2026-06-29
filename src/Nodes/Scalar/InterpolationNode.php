<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Scalar;

use PhpSyntax\Node;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * Braced interpolation inside a string: {$expr} or ${name}.
 */
final class InterpolationNode extends Node
{
	public const Slots = ['openBrace', 'expression', 'closeBrace'];


	/** @internal */
	public function __construct(
		public Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
