<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Type;

use PhpSyntax\Nodes\TypeNode;
use PhpSyntax\Token;


/**
 * Nullable type: ?T.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class NullableTypeNode extends TypeNode
{
	public const Slots = ['question', 'type'];

	public Token $question { set => $this->prepareSlot(__PROPERTY__, $value); }
	public NamedTypeNode $type { set => $this->prepareSlot(__PROPERTY__, $value); }


	/** @internal */
	public function __construct(Token $question, NamedTypeNode $type)
	{
		$this->question = $question;
		$this->type = $type;
	}
}
