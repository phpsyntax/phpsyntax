<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ArgumentListNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * exit or die with optional arguments.
 */
final class ExitNode extends ExpressionNode
{
	public const Slots = ['exitKeyword', 'arguments'];


	/** @internal */
	public function __construct(
		public Token $exitKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ArgumentListNode $arguments { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
