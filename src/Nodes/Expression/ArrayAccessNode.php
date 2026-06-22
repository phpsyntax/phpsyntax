<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * Array or string offset access: $a[$i], $a[].
 */
final class ArrayAccessNode extends ExpressionNode
{
	public const Slots = ['expression', 'openBracket', 'index', 'closeBracket'];


	/** @internal */
	public function __construct(
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $openBracket { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ExpressionNode $index { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeBracket { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
