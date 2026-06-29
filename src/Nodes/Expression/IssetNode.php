<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Token;


/**
 * isset(...) with one or more variables.
 */
final class IssetNode extends ExpressionNode
{
	public const Slots = ['issetKeyword', 'openParen', 'variables', 'closeParen'];


	/** @internal */
	public function __construct(
		public Token $issetKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var SeparatedNodeList<ExpressionNode> */
		public SeparatedNodeList $variables { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
