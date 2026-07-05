<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\CatchNode;
use PhpSyntax\Nodes\FinallyNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * try statement with catches and an optional finally.
 */
final class TryNode extends StatementNode
{
	public const Slots = ['tryKeyword', 'body', 'catches', 'finally'];


	/** @internal */
	public function __construct(
		public Token $tryKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public BlockNode $body { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var NodeList<CatchNode> */
		public NodeList $catches { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?FinallyNode $finally { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
