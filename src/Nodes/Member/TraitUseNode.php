<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\Nodes\MemberNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Token;


/**
 * use of traits with optional adaptations in braces.
 */
final class TraitUseNode extends MemberNode
{
	public const Slots = ['useKeyword', 'traits', 'semicolon', 'openBrace', 'adaptations', 'closeBrace'];


	/** @internal */
	public function __construct(
		public Token $useKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<NameNode> */
		public SeparatedNodeList $traits { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var ?NodeList<TraitAdaptationNode> */
		public ?NodeList $adaptations { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
