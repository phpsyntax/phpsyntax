<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Token;


/**
 * Arm of a match expression: the values compared with the subject, or default, and the result.
 */
final class MatchArmNode extends Node
{
	public const Slots = ['values', 'defaultKeyword', 'defaultComma', 'doubleArrow', 'body'];


	/** @internal */
	public function __construct(
		/** @var ?SeparatedNodeList<ExpressionNode> */
		public ?SeparatedNodeList $values { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $defaultKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $defaultComma { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $doubleArrow { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $body { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
