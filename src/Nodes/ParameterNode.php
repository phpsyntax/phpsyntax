<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\Member\PropertyHookNode;


/**
 * Parameter of a function, method, closure, arrow function or hook; with modifiers it promotes a property.
 */
final class ParameterNode extends Node
{
	public const Slots = ['attributes', 'modifiers', 'type', 'ampersand', 'ellipsis', 'variable', 'equals', 'default', 'openBrace', 'hooks', 'closeBrace'];


	/** @internal */
	public function __construct(
		/** @var NodeList<AttributeGroupNode> */
		public NodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ModifiersNode $modifiers { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?TypeNode $type { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $ampersand { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $ellipsis { set => $this->prepareSlot(__PROPERTY__, $value); },
		public VariableNode $variable { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $equals { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?ExpressionNode $default { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $openBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var ?NodeList<PropertyHookNode> */
		public ?NodeList $hooks { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ?Token $closeBrace { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/** Whether the parameter declares a property of the class, which its modifiers make it do. */
	public function isPromoted(): bool
	{
		return !$this->modifiers->isEmpty();
	}
}
