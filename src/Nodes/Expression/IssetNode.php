<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ExpressionNode, SeparatedNodeList};
use PhpSyntax\Token;


/**
 * `isset(...)` with one or more variables.
 */
final class IssetNode extends ExpressionNode
{
	public const Slots = ['issetKeyword', 'openParen', 'variables', 'closeParen'];


	/** @internal */
	public function __construct(
		public Token $issetKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ExpressionNode> */
		public SeparatedNodeList $variables { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
