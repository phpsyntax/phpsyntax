<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\{ElseIfNode, ElseNode, ExpressionNode, NodeList, StatementNode};
use PhpSyntax\Token;


/**
 * `if` statement in either syntax; the body is a statement, the alternative syntax fills statements.
 */
final class IfNode extends StatementNode
{
	public const Slots = ['ifKeyword', 'openParen', 'condition', 'closeParen', 'body', 'colon', 'statements', 'elseifs', 'else', 'endKeyword', 'semicolon'];


	/** @internal */
	public function __construct(
		public Token $ifKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $condition { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?StatementNode $body { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var ?NodeList<StatementNode> */
		public ?NodeList $statements { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var NodeList<ElseIfNode> */
		public NodeList $elseifs { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ElseNode $else { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $endKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
