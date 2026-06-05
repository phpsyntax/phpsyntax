<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ExpressionNode, NodeList};
use PhpSyntax\Nodes\Scalar\{InterpolatedStringPartNode, InterpolationNode};
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
