<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Member;

use PhpSyntax\Node;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * One property of a declaration with an optional default.
 */
final class PropertyItemNode extends Node
{
	public const Slots = ['name', 'equals', 'default'];

	/** The name of the property without the dollar sign. */
	public string $plainName {
		get => substr($this->name->text, 1);
	}


	/** @internal */
	public function __construct(
		public Token $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $equals { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ExpressionNode $default { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
