<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{SeparatedNodeList, StatementNode, StaticVariableNode};
use PhpSyntax\Token;


/**
 * `static` variable declaration.
 */
final class StaticNode extends StatementNode
{
	public const Slots = ['staticKeyword', 'variables', 'semicolon'];


	/** @internal */
	public function __construct(
		public Token $staticKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<StaticVariableNode> */
		public SeparatedNodeList $variables { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
