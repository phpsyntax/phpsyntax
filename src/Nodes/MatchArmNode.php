<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};


/**
 * Arm of a `match` expression: the values compared with the subject, or `default`, and the result.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class MatchArmNode extends Node
{
	public const Slots = ['values', 'defaultKeyword', 'comma', 'doubleArrow', 'result'];

	/** @var ?SeparatedNodeList<ExpressionNode> */
	public ?SeparatedNodeList $values = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $defaultKeyword = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $comma = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public Token $doubleArrow { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ExpressionNode $result { set => $this->prepareSlot(__PROPERTY__, $value); }


	/**
	 * @internal
	 * @param ?SeparatedNodeList<ExpressionNode> $values
	 */
	public function __construct(
		?SeparatedNodeList $values,
		?Token $defaultKeyword,
		?Token $comma,
		Token $doubleArrow,
		ExpressionNode $result,
	) {
		$values === null || $this->values = $values;
		$defaultKeyword === null || $this->defaultKeyword = $defaultKeyword;
		$comma === null || $this->comma = $comma;
		$this->doubleArrow = $doubleArrow;
		$this->result = $result;
	}
}
