<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Scalar;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\ScalarNode;
use PhpSyntax\Token;


/**
 * Double-quoted string with interpolated variables or expressions.
 */
final class InterpolatedStringNode extends ScalarNode
{
	public const Slots = ['openQuote', 'parts', 'closeQuote'];


	/** @internal */
	public function __construct(
		public Token $openQuote { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var NodeList<InterpolatedStringPartNode|InterpolationNode|ExpressionNode> */
		public NodeList $parts { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeQuote { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
