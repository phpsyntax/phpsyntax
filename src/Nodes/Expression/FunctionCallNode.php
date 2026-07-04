<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ArgumentListNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\NameNode;


/**
 * Function call by name or on an expression.
 */
final class FunctionCallNode extends ExpressionNode
{
	public const Slots = ['name', 'arguments'];


	/** @internal */
	public function __construct(
		public NameNode|ExpressionNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ArgumentListNode $arguments { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
