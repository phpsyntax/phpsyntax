<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\Nodes\IdentifierNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Token;


/**
 * Trait adaptation m as [modifier] [alias];.
 */
final class TraitAliasNode extends TraitAdaptationNode
{
	public const Slots = ['trait', 'doubleColon', 'method', 'asKeyword', 'modifier', 'alias', 'semicolon'];


	/** @internal */
	public function __construct(
		public ?NameNode $trait { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $doubleColon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public IdentifierNode $method { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $asKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $modifier { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?IdentifierNode $alias { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
