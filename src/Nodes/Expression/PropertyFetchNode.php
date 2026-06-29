<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\IdentifierNode;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;


/**
 * Property access with -> or ?->; the name may be an identifier, a variable or a braced expression.
 */
final class PropertyFetchNode extends ExpressionNode
{
	public const Slots = ['object', 'operator', 'openBrace', 'name', 'closeBrace'];


	/** @internal */
	public function __construct(
		public ExpressionNode $object { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		public IdentifierNode|ExpressionNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/** Whether the fetch is written with ?->, which skips it when the object is null. */
	public function isNullsafe(): bool
	{
		return $this->operator->kind === TokenKind::NullsafeObjectOperator;
	}
}
