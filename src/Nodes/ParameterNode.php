<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{Node, Surgery, Token, Trivia};
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\Member\PropertyHookNode;
use function count;


/**
 * Parameter of a function, method, closure, arrow function or hook; with modifiers it promotes a property.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
final class ParameterNode extends Node implements AttributeAwareNode
{
	public const Slots = ['attributes', 'modifiers', 'type', 'ampersand', 'ellipsis', 'variable', 'equals', 'default', 'openBrace', 'hooks', 'closeBrace'];

	/** @var PlainNodeList<AttributeGroupNode> */
	public PlainNodeList $attributes { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ModifiersNode $modifiers { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?TypeNode $type = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $ampersand = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $ellipsis = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public VariableNode $variable { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $equals = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?ExpressionNode $default = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $openBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** @var ?PlainNodeList<PropertyHookNode> */
	public ?PlainNodeList $hooks = null { set => $this->prepareSlot(__PROPERTY__, $value); }
	public ?Token $closeBrace = null { set => $this->prepareSlot(__PROPERTY__, $value); }

	/** Whether the parameter declares a property of the class, which its modifiers make it do. */
	public bool $promoted {
		get => !$this->modifiers->isEmpty();
	}


	/**
	 * @internal
	 * @param PlainNodeList<AttributeGroupNode> $attributes
	 * @param ?PlainNodeList<PropertyHookNode> $hooks
	 */
	public function __construct(
		PlainNodeList $attributes,
		ModifiersNode $modifiers,
		?TypeNode $type,
		?Token $ampersand,
		?Token $ellipsis,
		VariableNode $variable,
		?Token $equals,
		?ExpressionNode $default,
		?Token $openBrace,
		?PlainNodeList $hooks,
		?Token $closeBrace,
	) {
		$this->attributes = $attributes;
		$this->modifiers = $modifiers;
		$type === null || $this->type = $type;
		$ampersand === null || $this->ampersand = $ampersand;
		$ellipsis === null || $this->ellipsis = $ellipsis;
		$this->variable = $variable;
		$equals === null || $this->equals = $equals;
		$default === null || $this->default = $default;
		$openBrace === null || $this->openBrace = $openBrace;
		$hooks === null || $this->hooks = $hooks;
		$closeBrace === null || $this->closeBrace = $closeBrace;
	}


	/**
	 * Writes the type before the variable with one space after it, or removes it with its space, its comments
	 * staying; a type standing in a tree comes as a copy without the trivia on its edges.
	 */
	public function setType(?TypeNode $type): static
	{
		Surgery::writeType($this, $type);
		return $this;
	}


	/**
	 * Doc comment of the parameter: the one after it, before the separator or before the token after it where the last
	 * parameter has none, which PHP reads first, and else the one before it as for any node.
	 */
	public function getDocComment(): ?Trivia
	{
		$last = $this->getLastToken();
		foreach ([$last->getNext()->leadingTrivia ?? [], $last->trailingTrivia] as $trivias) {
			for ($i = count($trivias) - 1; $i >= 0; $i--) {
				if ($trivias[$i]->is(Trivia::DocComment) && !$trivias[$i]->inInterpolation) {
					return $trivias[$i];
				}
			}
		}

		return parent::getDocComment();
	}
}
