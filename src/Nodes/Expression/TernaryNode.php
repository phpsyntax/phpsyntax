<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\OperatorNode;
use PhpSyntax\Token;


/**
 * Ternary conditional `$a ? $b : $c`, or the elvis form `$a ?: $c` leaving `then` empty.
 */
final class TernaryNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['condition', 'question', 'then', 'colon', 'else'];


	/** @internal */
	public function __construct(
		public ExpressionNode $condition { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $question { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ExpressionNode $then { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $else { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	public function getPrecedence(): array
	{
		return [100, self::NonAssociative];
	}
}
