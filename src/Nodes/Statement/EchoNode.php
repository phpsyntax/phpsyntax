<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{ExpressionNode, SeparatedNodeList, StatementNode};
use PhpSyntax\Token;


/**
 * `echo` statement; the keyword may be the `<?=` open tag and the semicolon a close tag.
 */
final class EchoNode extends StatementNode
{
	public const Slots = ['echoKeyword', 'expressions', 'semicolon'];


	/** @internal */
	public function __construct(
		public Token $echoKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ExpressionNode> */
		public SeparatedNodeList $expressions { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
