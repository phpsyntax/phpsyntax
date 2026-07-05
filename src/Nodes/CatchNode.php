<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\Statement\BlockNode;
use PhpSyntax\Token;


/**
 * catch clause with one or more types and an optional variable.
 */
final class CatchNode extends Node
{
	public const Slots = ['catchKeyword', 'openParen', 'types', 'variable', 'closeParen', 'body'];


	/** @internal */
	public function __construct(
		public Token $catchKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<NameNode> */
		public SeparatedNodeList $types { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?VariableNode $variable { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public BlockNode $body { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
