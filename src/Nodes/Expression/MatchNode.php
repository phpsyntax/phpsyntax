<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\{ExpressionNode, MatchArmNode, SeparatedNodeList};
use PhpSyntax\Token;


/**
 * `match` expression.
 */
final class MatchNode extends ExpressionNode
{
	public const Slots = ['matchKeyword', 'openParen', 'subject', 'closeParen', 'openBrace', 'arms', 'closeBrace'];


	/** @internal */
	public function __construct(
		public Token $matchKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $subject { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<MatchArmNode> */
		public SeparatedNodeList $arms { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
