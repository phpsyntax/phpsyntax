<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * __halt_compiler(); the rest of the file is the data token.
 */
final class HaltCompilerNode extends StatementNode
{
	public const Slots = ['haltKeyword', 'openParen', 'closeParen', 'semicolon', 'data'];


	/** @internal */
	public function __construct(
		public Token $haltKeyword { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $data { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
