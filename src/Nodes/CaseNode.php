<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * `case` or `default` of a `switch`; the separator is a colon or a semicolon.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class CaseNode extends Node
{
	public const Slots = ['keyword', 'value', 'separator', 'statements'];

	public Token $keyword { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ExpressionNode $value = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $separator { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var PlainNodeList<StatementNode> */
	public PlainNodeList $statements { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param PlainNodeList<StatementNode> $statements
	 */
	public function __construct(Token $keyword, ?ExpressionNode $value, Token $separator, PlainNodeList $statements)
	{
		$this->keyword = $keyword;
		$value === null || $this->value = $value;
		$this->separator = $separator;
		$this->statements = $statements;
	}
}
