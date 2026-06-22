<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\Scalar\InterpolatedStringPartNode;
use PhpSyntax\Nodes\Scalar\InterpolationNode;
use PhpSyntax\Token;


/**
 * Command in backticks with interpolation.
 */
final class ShellExecNode extends ExpressionNode
{
	public const Slots = ['openBacktick', 'parts', 'closeBacktick'];


	/** @internal */
	public function __construct(
		public Token $openBacktick { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var NodeList<InterpolatedStringPartNode|InterpolationNode|ExpressionNode> */
		public NodeList $parts { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeBacktick { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
