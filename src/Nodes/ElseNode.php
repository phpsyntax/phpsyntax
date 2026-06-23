<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Token;


/**
 * else branch, in either syntax; else if is an else with an if statement as the body.
 */
final class ElseNode extends Node
{
	public const Slots = ['elseKeyword', 'body', 'colon', 'statements'];


	/** @internal */
	public function __construct(
		public Token $elseKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?StatementNode $body { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var ?NodeList<StatementNode> */
		public ?NodeList $statements { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
