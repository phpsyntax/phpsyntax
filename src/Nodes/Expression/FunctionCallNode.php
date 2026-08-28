<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\AccessKind;
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


	/** A call of the name, or of the expression, in parentheses where the call would take it for something else. */
	public static function of(NameNode|ExpressionNode $name, ?ArgumentListNode $arguments = null): self
	{
		$name->setEdgeTrivia([], []);
		if ($name instanceof ExpressionNode && !$name->isDereferenceable(AccessKind::Call)) {
			$name = ParenthesizedNode::of($name);
		}

		$arguments?->setEdgeTrivia([], []);
		return new self($name, $arguments ?? ArgumentListNode::of());
	}
}
