<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * include, include_once, require or require_once; the keyword token tells which.
 */
final class IncludeNode extends ExpressionNode
{
	public const Slots = ['includeKeyword', 'expression'];


	/** @internal */
	public function __construct(
		public Token $includeKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
