<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Type;

use PhpSyntax\Nodes\TypeNode;
use PhpSyntax\Token;


/**
 * Nullable type: ?T.
 */
final class NullableTypeNode extends TypeNode
{
	public const Slots = ['question', 'type'];


	/** @internal */
	public function __construct(
		public Token $question { set => $this->prepareSlot(__PROPERTY__, $value); },
		public TypeNode $type { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
