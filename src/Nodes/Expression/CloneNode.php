<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * clone expression; clone(...) with arguments is a function call.
 */
final class CloneNode extends ExpressionNode
{
	public const Slots = ['cloneKeyword', 'expression'];


	/** @internal */
	public function __construct(
		public Token $cloneKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
