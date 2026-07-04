<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\NameNode;


/**
 * Constant access by name: FOO, \Foo\BAR.
 */
final class ConstantFetchNode extends ExpressionNode
{
	public const Slots = ['name'];


	/** @internal */
	public function __construct(
		public NameNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
