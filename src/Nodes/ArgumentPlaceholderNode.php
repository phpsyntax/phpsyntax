<?php declare(strict_types=1);

namespace PhpSyntax\Nodes;

use PhpSyntax\Node;
use PhpSyntax\Token;


/**
 * The ? placeholder of a partial function application, optionally named: f(?), f(name: ?).
 */
final class ArgumentPlaceholderNode extends Node
{
	public const Slots = ['name', 'colon', 'question'];


	/** @internal */
	public function __construct(
		public ?IdentifierNode $name { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $colon { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $question { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}
}
