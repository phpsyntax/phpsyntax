<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Token;


/**
 * Static property access: A::$b, A::$$b, A::${expr}. The name is written the way a variable is, dollar
 * and braces included, but it names a property.
 */
final class StaticPropertyFetchNode extends ExpressionNode
{
	public const Slots = ['class', 'doubleColon', 'dollar', 'openBrace', 'name', 'closeBrace'];

	/** The name of the property without the dollar sign; null where the name is an expression ($$b, ${expr}). */
	public ?string $plainName {
		get => $this->name instanceof Token ? substr($this->name->text, 1) : null;
	}


	/** @internal */
	public function __construct(
		public NameNode|ExpressionNode $class { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $doubleColon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $dollar { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token|ExpressionNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
