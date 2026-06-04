<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Scalar;

use PhpSyntax\Nodes\{ExpressionNode, NodeList, ScalarNode};
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
