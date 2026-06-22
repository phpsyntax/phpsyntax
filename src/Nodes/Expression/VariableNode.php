<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;


/**
 * Variable: $a, $$a, ${expr}; inside a string also a bare name in ${name}.
 */
final class VariableNode extends ExpressionNode
{
	public const Slots = ['dollar', 'openBrace', 'name', 'closeBrace'];

	/** The name without the dollar sign; null where the name is an expression ($$a, ${expr}). */
	public ?string $plainName {
		get {
			$name = $this->name;
			return $name instanceof Token
				? substr($name->text, $name->kind === TokenKind::Variable ? 1 : 0) // a bare name in "${name}" carries none
				: null;
		}
	}


	/** @internal */
	public function __construct(
		public ?Token $dollar { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token|ExpressionNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
