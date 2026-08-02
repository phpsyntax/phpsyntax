<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\OperatorNode;
use PhpSyntax\Token;


/**
 * instanceof check against a name or a dynamic class.
 */
final class InstanceofNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['expression', 'instanceofKeyword', 'class'];


	/** @internal */
	public function __construct(
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $instanceofKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public NameNode|ExpressionNode $class { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	public function getPrecedence(): array
	{
		return [230, self::NonAssociative];
	}
}
