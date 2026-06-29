<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * echo statement; the keyword may be the <?= open tag and the semicolon a close tag.
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
