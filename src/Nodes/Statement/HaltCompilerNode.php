<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * `__halt_compiler()`; the rest of the file is the data token.
 */
final class HaltCompilerNode extends StatementNode
{
	public const Slots = ['haltCompilerKeyword', 'openParen', 'closeParen', 'semicolon', 'data'];

	public Token $haltCompilerKeyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $semicolon { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $data = null { set => $this->prepareSlot(__PROPERTY__, $value); }


	/** @internal */
	public function __construct(Token $haltCompilerKeyword, Token $openParen, Token $closeParen, Token $semicolon, ?Token $data)
	{
		$this->haltCompilerKeyword = $haltCompilerKeyword;
		$this->openParen = $openParen;
		$this->closeParen = $closeParen;
		$this->semicolon = $semicolon;
		$data === null || $this->data = $data;
	}
}
