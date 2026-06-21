<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Scalar;

use PhpSyntax\Nodes\ScalarNode;
use PhpSyntax\Token;


/**
 * Name of an offset written without quotes inside an interpolated string ("$row[label]"), which PHP reads
 * as a string; having no delimiter, it stands for itself and holds no escape sequence.
 */
final class UnquotedStringNode extends ScalarNode
{
	public const Slots = ['token'];

	/** The value of the offset, which is the text as it stands. */
	public string $value {
		get => $this->token->text;
	}


	/** @internal */
	public function __construct(
		public Token $token { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
