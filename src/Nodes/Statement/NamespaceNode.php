<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * namespace declaration; after "namespace A;" the following statements are nested in it.
 */
final class NamespaceNode extends StatementNode
{
	public const Slots = ['namespaceKeyword', 'name', 'semicolon', 'openBrace', 'statements', 'closeBrace'];


	/** @internal */
	public function __construct(
		public Token $namespaceKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?NameNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var NodeList<StatementNode> */
		public NodeList $statements { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
