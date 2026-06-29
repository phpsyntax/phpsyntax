<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;


/**
 * Type cast; the cast token keeps its spelling including inner whitespace: ( int ).
 */
final class CastNode extends ExpressionNode
{
	public const Slots = ['cast', 'expression'];

	/** The type the cast converts to, in the name PHP knows it by: (integer) is int, (double) and (real) are float. */
	public string $type {
		get {
			$name = strtolower(trim($this->cast->text, "() \t\r\n"));
			return match ($name) {
				'integer' => 'int',
				'boolean' => 'bool',
				'double', 'real' => 'float',
				'binary' => 'string',
				default => $name,
			};
		}
	}


	/** @internal */
	public function __construct(
		public Token $cast { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
