<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * `case` or `default` of a `switch`; the separator is a colon or a semicolon.
 */
final class CaseNode extends Node
{
	public const Slots = ['caseKeyword', 'value', 'separator', 'statements'];


	/** @internal */
	public function __construct(
		public Token $caseKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ExpressionNode $value { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $separator { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var NodeList<StatementNode> */
		public NodeList $statements { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
