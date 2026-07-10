<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes;

use PhpSyntax\{DereferenceKind, Helpers, Node, Token, Trivia};
use PhpSyntax\Nodes\Expression\{ArrayAccessNode, ArrayNode, AssignmentByReferenceNode, AssignmentNode, BinaryOpNode, CastNode, ClassConstantFetchNode, CombinedAssignmentNode, ConstantFetchNode, EmptyNode, FunctionCallNode, InstanceofNode, IssetNode, MethodCallNode, ParenthesizedNode, PostfixOpNode, PrefixOpNode, PropertyFetchNode, ShellExecNode, StaticMethodCallNode, StaticPropertyFetchNode, UnaryOpNode, VariableNode};
use PhpSyntax\Nodes\Scalar\{BooleanNode, NullNode};
use PhpSyntax\Nodes\Statement\{ForeachNode, GlobalNode, UnsetNode};
use function is_array, is_float, is_int, is_string;


/**
 * Expression, which stands for a value; a destructuring stands where a target is written and is a DestructuringNode,
 * not one of these.
 * @method Token getFirstToken()
 * @method Token getLastToken()
 */
abstract class ExpressionNode extends Node
{
	/**
	 * How the parent reaches into this expression, null where it does not: `$this->x`, `$this->y()` and
	 * `$this[0]` fetch from it, `$this()` calls it, `$this::y()` takes it for the name of a class.
	 */
	public function getDereferenceKind(): ?DereferenceKind
	{
		$parent = $this->parent;
		return match (true) {
			$parent instanceof MethodCallNode, $parent instanceof PropertyFetchNode => $parent->object === $this ? DereferenceKind::Fetch : null,
			$parent instanceof ArrayAccessNode => $parent->expression === $this ? DereferenceKind::Fetch : null,
			$parent instanceof FunctionCallNode => DereferenceKind::Call,
			$parent instanceof StaticMethodCallNode, $parent instanceof StaticPropertyFetchNode, $parent instanceof ClassConstantFetchNode => $parent->class === $this ? DereferenceKind::StaticAccess : null,
			default => null,
		};
	}


	/**
	 * Whether the parent reads a member, an element or a static member of this expression, or calls it:
	 * `$this->x`, `$this[0]`, `$this::y()`, `$this()`. Such an expression needs parentheses unless the
	 * grammar makes it a primary one.
	 */
	public function isDereferenced(): bool
	{
		return $this->getDereferenceKind() !== null;
	}


	/**
	 * Whether what is written after the expression may reach into it with no parentheses around it.
	 * A call takes a name or a member written before it for its own, which calls another thing entirely,
	 * and `::` takes the name of a class, which a name written there would become.
	 */
	public function isDereferenceable(DereferenceKind $by = DereferenceKind::Fetch): bool
	{
		return $this instanceof VariableNode
			|| $this instanceof ArrayAccessNode
			|| $this instanceof FunctionCallNode
			|| $this instanceof MethodCallNode
			|| $this instanceof StaticMethodCallNode
			|| $this instanceof ParenthesizedNode
			|| ($by !== DereferenceKind::Call && (
				$this instanceof PropertyFetchNode
				|| $this instanceof StaticPropertyFetchNode
				|| $this instanceof ClassConstantFetchNode
			))
			|| ($by === DereferenceKind::Fetch && (
				$this instanceof ConstantFetchNode
				|| $this instanceof BooleanNode
				|| $this instanceof NullNode
			));
	}


	/**
	 * Whether the expression may stand where a class is named: a variable, a static property of a named class
	 * and what is read out of either all the way down, a property or an element, and an expression in
	 * parentheses; `new f()->b` instantiates f and `new A::B[0]` is no code.
	 * @internal what `ParenthesizedNode::isRedundant()` asks
	 */
	public function canNameClass(): bool
	{
		return match (true) {
			$this instanceof VariableNode, $this instanceof ParenthesizedNode => true,
			$this instanceof PropertyFetchNode => self::isReadOutOfVariable($this->object),
			$this instanceof ArrayAccessNode => self::isReadOutOfVariable($this->expression),
			$this instanceof StaticPropertyFetchNode => $this->class instanceof NameNode || self::isReadOutOfVariable($this->class),
			default => false,
		};
	}


	private static function isReadOutOfVariable(self $expression): bool
	{
		return !$expression instanceof ParenthesizedNode && $expression->canNameClass();
	}


	/**
	 * Whether the expression may be written inside a double-quoted string, a heredoc or a shell command in braces,
	 * `{$...}`: what the grammar reads as a variable there, which is a variable, an element, a property, a static
	 * property or a call of a function, a method or a static method, starting with the dollar of a variable.
	 */
	public function canStandInString(): bool
	{
		$first = $this->getFirstToken();
		return ($first->is(Token::Variable) || $first->is('$'))
			&& (
				$this instanceof VariableNode
				|| ($this instanceof ArrayAccessNode && $this->index !== null)
				|| $this instanceof PropertyFetchNode
				|| $this instanceof StaticPropertyFetchNode
				|| $this instanceof FunctionCallNode
				|| $this instanceof MethodCallNode
				|| $this instanceof StaticMethodCallNode
			);
	}


	/**
	 * Whether the expression may stand where a place is assigned to: a variable, an element, a property,
	 * reached through a chain PHP writes through. A `?->` anywhere along it rules the write out, a call in the
	 * chain included, and so does a chain starting at a value of its own, a literal, a constant, `new` or
	 * `clone`, which has no place to write to. It says nothing about reading; that is `isRepeatableRead()`,
	 * and the two answer for the two sides of `=`.
	 */
	public function isWritable(): bool
	{
		if (
			!$this instanceof VariableNode
			&& !$this instanceof ArrayAccessNode
			&& !$this instanceof StaticPropertyFetchNode
			&& !$this instanceof PropertyFetchNode
		) {
			return false;
		}

		if ($this->isInNullsafeChain()) {
			return false;
		}

		$node = $this;
		while (($inner = $node->reachInto()) !== null) {
			$node = $inner;
		}

		// the start of the chain, which a variable, a call or a static member of a named class can be
		return $node instanceof VariableNode
			|| $node instanceof FunctionCallNode
			|| $node instanceof StaticPropertyFetchNode
			|| $node instanceof StaticMethodCallNode;
	}


	/**
	 * Whether a `?->` stands in the chain the expression reads out of, its own access and parentheses included: PHP
	 * refuses to write there or to make a first-class callable of a call there, the `?->` possibly skipping the rest.
	 */
	public function isInNullsafeChain(): bool
	{
		for ($node = $this; $node !== null; $node = $node->reachInto()) {
			if (($node instanceof PropertyFetchNode || $node instanceof MethodCallNode) && $node->nullsafe) {
				return true;
			}
		}

		return false;
	}


	/**
	 * The expression this one reads out of, which is the chain a write reaches through; null where it reads
	 * out of nothing, a function call, a named class and the index of an element among them.
	 */
	private function reachInto(): ?self
	{
		return match (true) {
			$this instanceof PropertyFetchNode, $this instanceof MethodCallNode => $this->object,
			$this instanceof ArrayAccessNode, $this instanceof ParenthesizedNode => $this->expression,
			$this instanceof StaticPropertyFetchNode, $this instanceof StaticMethodCallNode => $this->class instanceof self ? $this->class : null,
			default => null,
		};
	}


	/**
	 * Whether something writes the expression or an element of it, a variable, a property or an element alike: it is
	 * assigned, stepped, unset, bound, or taken by reference, either side of `=&` among them, the right one becoming
	 * a reference a write may later go through.
	 */
	public function isWritten(): bool
	{
		$node = $this;
		$parent = $node->parent;
		while ( // destructuring writes every value inside it, not a key, and unset and global take theirs in a list
			($parent instanceof ArrayAccessNode && $parent->expression === $node)
			|| $parent instanceof ParenthesizedNode
			|| $parent instanceof ArrayNode
			|| $parent instanceof DestructuringNode
			|| ($parent instanceof ArrayItemNode && $parent->value === $node)
			|| $parent instanceof SeparatedNodeList
		) {
			if ($parent instanceof ArrayItemNode && $parent->ampersand !== null) {
				return true; // [&$a] takes it by reference
			}

			[$node, $parent] = [$parent, $parent->parent];
		}

		return match (true) {
			$parent instanceof AssignmentNode,
			$parent instanceof CombinedAssignmentNode => $parent->findSlotOf($node) === 'target',
			$parent instanceof AssignmentByReferenceNode,
			$parent instanceof PrefixOpNode,
			$parent instanceof PostfixOpNode => true,
			$parent instanceof ArgumentNode, $parent instanceof ClosureUseNode => $parent->ampersand !== null,
			$parent instanceof ForeachNode => $parent->findSlotOf($node) === 'key' || $parent->findSlotOf($node) === 'value',
			$parent instanceof UnsetNode,
			$parent instanceof GlobalNode => true,
			$parent instanceof StaticVariableNode, $parent instanceof CatchNode => $parent->variable === $node,
			default => false,
		};
	}


	/**
	 * Whether reading the expression again gives the same value with no side effects: variables, property,
	 * constant and offset fetches, literals, arrays of such items and what parentheses or a unary operator make
	 * of them, nothing that runs code of its own; an unpacked item may run a generator and one by reference
	 * creates the variable, so neither is repeatable. The answer is syntactic, so what the language runs behind
	 * such a read is out of sight and does not count: a magic getter or a property hook behind a fetch, an
	 * `ArrayAccess` behind an offset, a `__toString()` behind a string that interpolates. Whoever cannot assume that
	 * much has to know the types, which a syntax tree does not.
	 */
	public function isRepeatableRead(): bool
	{
		foreach ([$this, ...$this->find(Node::class)] as $node) {
			// what is not an expression runs nothing of its own: lists, names, the pieces of a string
			if (
				($node instanceof ArrayItemNode && ($node->ellipsis !== null || $node->ampersand !== null))
				|| (
					$node instanceof self
					&& !$node instanceof VariableNode
					&& !$node instanceof ArrayAccessNode
					&& !$node instanceof PropertyFetchNode
					&& !$node instanceof StaticPropertyFetchNode
					&& !$node instanceof ClassConstantFetchNode
					&& !$node instanceof ConstantFetchNode
					&& !$node instanceof ScalarNode
					&& !$node instanceof ArrayNode
					&& !$node instanceof ParenthesizedNode
					&& !$node instanceof UnaryOpNode
				)
			) {
				return false;
			}
		}

		return true;
	}


	/**
	 * Whether the expression yields a boolean whatever its operands: a comparison, a logical operation, a negation,
	 * `instanceof`, `isset()`, `empty()`, a bool cast or a boolean literal.
	 */
	public function evaluatesToBoolean(): bool
	{
		return match (true) {
			$this instanceof BinaryOpNode => $this->isLogical() || $this->operator->is([
				Token::IsEqual, Token::IsNotEqual, Token::IsIdentical, Token::IsNotIdentical,
				'<', '>', Token::IsSmallerOrEqual, Token::IsGreaterOrEqual,
			]),
			$this instanceof UnaryOpNode => $this->operator->is('!'),
			$this instanceof CastNode => $this->operator->is(Token::BoolCast),
			$this instanceof ParenthesizedNode => $this->expression->evaluatesToBoolean(),
			default => $this instanceof BooleanNode || $this instanceof InstanceofNode || $this instanceof IssetNode || $this instanceof EmptyNode,
		};
	}


	/**
	 * The value the expression is written as: a scalar, `null`, `true`, `false`, or an array of them. A name
	 * standing for a constant is not one, its value being a matter of what the code around it defines.
	 * @throws \LogicException  where the expression is written as no value; `hasValue()` tells beforehand
	 */
	public function toValue(): mixed
	{
		$value = $this->readValue();
		return $value === null
			? throw new \LogicException('Expression ' . Helpers::formatCode($this->text) . ' has no value of its own.')
			: $value[0];
	}


	/** Whether the expression is written as a value, which is what `toValue()` gives. */
	public function hasValue(): bool
	{
		return $this->readValue() !== null;
	}


	/** @return ?array{mixed}  the value in a list, so that null tells no value apart from the value null */
	private function readValue(): ?array
	{
		if (
			$this instanceof Scalar\IntegerNode
			|| $this instanceof Scalar\FloatNode
			|| $this instanceof Scalar\StringNode
			|| $this instanceof Scalar\UnquotedStringNode
			|| $this instanceof Scalar\BooleanNode
		) {
			return [$this->toValue()];

		} elseif ($this instanceof Scalar\HeredocNode) {
			return $this->hasInterpolation() ? null : [$this->toValue()];

		} elseif ($this instanceof Expression\ParenthesizedNode) {
			return $this->expression->readValue();

		} elseif ($this instanceof Scalar\NullNode) {
			return [null];

		} elseif ($this instanceof Expression\UnaryOpNode) {
			$value = $this->operator->is(['-', '+']) ? $this->expression->readValue() : null;
			return is_int($value[0] ?? null) || is_float($value[0] ?? null)
				? [$this->operator->is('-') ? -$value[0] : $value[0]]
				: null;

		} elseif ($this instanceof Expression\ArrayNode) {
			return self::readArrayValue($this);
		}

		return null;
	}


	/** @return ?array{array<array-key, mixed>} */
	private static function readArrayValue(Expression\ArrayNode $array): ?array
	{
		$result = [];
		foreach ($array->items as $item) {
			if (!$item instanceof ArrayItemNode || $item->ampersand) {
				return null;
			}

			// an item that is a destructuring target has no value
			$value = $item->value instanceof self ? $item->value->readValue() : null;
			if ($value === null) {
				return null;

			} elseif ($item->ellipsis) {
				if (!is_array($value[0])) {
					return null;
				}

				foreach ($value[0] as $key => $element) { // spreading renumbers what it brings, not what is there
					if (is_string($key)) {
						$result[$key] = $element;
					} else {
						$result[] = $element;
					}
				}

			} elseif ($item->key === null) {
				$result[] = $value[0];

			} else {
				$key = $item->key->readValue();
				if ($key === null || (!is_int($key[0]) && !is_string($key[0]))) {
					return null;
				}

				$result[$key[0]] = $value[0];
			}
		}

		return [$result];
	}


	/**
	 * Replaces this node by the expression the way `replaceWith()` does, in parentheses where the expression
	 * binds looser than the place asks or is reached into there: what `ParenthesizedNode::isRedundant()`
	 * does not call needless stays. A place typed narrower, which takes no parentheses, gets the expression bare.
	 * The name of a member or of a variable written as a bare variable (`$o->$m`, `A::$m`, `$$m`) takes a variable
	 * bare and anything else in braces (`$o->{$a . 'b'}`), which is what the grammar reads there.
	 *
	 * Inside a string, a heredoc or a shell command the grammar of interpolation decides. What begins the expression
	 * of `{$...}` takes only an expression that `canStandInString()`, written as it is, and so does a part standing
	 * bare among the parts of the string, in braces where the bare form would read it otherwise; a place inside a
	 * bare part keeps the bare form for a plain variable and turns the part into `{$...}` for anything else. The name
	 * of `${name}` is refused, an expression there naming a variable variable, and so are braces that would follow
	 * a dollar of the text and read as `${`. The trivia the expression brings are marked as standing in the string, and
	 * outside a string they lose such a mark, but for those inside a string the expression holds.
	 * @throws \InvalidArgumentException  for an expression the place cannot hold, before anything moves, as
	 *   `checkReplaceWithExpression()` tells beforehand
	 */
	public function replaceWithExpression(self $expression): void
	{
		$this->checkReplaceWithExpression($expression);
		$part = $this->findStringPart();
		if ($part !== null) {
			$this->replaceInString($expression, $part);
		} else {
			$this->replaceInCode($expression);
			self::markInterpolated($expression->parent instanceof ParenthesizedNode ? $expression->parent : $expression, false);
		}
	}


	/**
	 * The dry run of `replaceWithExpression()`: throws what the write would refuse the expression with, and moves
	 * nothing. A slot that does not take it is refused, a place in a string it cannot stand in, braces after a dollar
	 * of the text and the name of `${name}`.
	 * @throws \InvalidArgumentException  for an expression the place cannot hold
	 * @throws \LogicException  for a node without a parent and an expression the write cannot take from where it stands
	 */
	public function checkReplaceWithExpression(self $expression): void
	{
		$parent = $this->parent ?? throw new \LogicException('A node without a parent cannot be replaced.');
		if ($expression === $this) {
			return;
		}

		$parent->checkValue($expression, $this);
		$this->checkPlaceHolds($expression);
	}


	/**
	 * Refuses an expression the place of this node cannot hold, wherever the expression stands now: what
	 * `checkReplaceWithExpression()` asks beyond the ownership of the expression.
	 * @internal the builder asks it of a part before it takes any
	 * @throws \InvalidArgumentException  for an expression the place cannot hold
	 */
	public function checkPlaceHolds(self $expression): void
	{
		$parent = $this->parent ?? throw new \LogicException('A node without a parent cannot be replaced.');
		$part = $this->findStringPart();
		if ($part === null) {
			$this->checkInCode($expression);

		} elseif ($part instanceof Scalar\InterpolationNode && !$part->openBrace->is(Token::CurlyOpen)) { // ${...}
			if ($this->getFirstToken()->is(Token::StringVariableName)) {
				throw new \InvalidArgumentException('The name ' . Helpers::formatCode($this->text) . ' of `${...}` cannot be replaced by an expression, which would name a variable variable there.');
			}

			$this->checkInCode($expression);

		} elseif ($part instanceof Scalar\InterpolationNode) { // {$...}
			if ($this->getFirstToken() !== $part->expression->getFirstToken()) {
				$this->checkInCode($expression);
			} else { // what begins the braces is read as a variable
				self::checkStandsInString($expression);
			}

		} elseif ($part === $this) {
			self::checkStandsInString($expression);
			if (!$expression->fitsBareInString($this)) {
				self::checkBracesFit($part);
			}

		} elseif ($part instanceof self && ($parent !== $part || !self::isPlainVariable($expression))) { // the part turns into `{$...}`
			if ($this->getFirstToken() === $part->getFirstToken()) {
				self::checkStandsInString($expression);
			} else {
				$this->checkInCode($expression);
			}

			self::checkBracesFit($part);
		}
	}


	/** Refuses an expression the slot of this node does not take where the grammar of code writes it bare. */
	private function checkInCode(self $expression): void
	{
		$parent = $this->parent ?? throw new \LogicException('A node without a parent cannot be replaced.');
		$bare = $expression instanceof ParenthesizedNode || $this->isBareName() || !self::canHold($parent, $this, ParenthesizedNode::class);
		if ($bare && !self::canHold($parent, $this, $expression::class)) {
			throw new \InvalidArgumentException('`' . $expression::class . '` cannot be placed in the slot `' . $parent->findSlotOf($this) . '` of `' . $parent::class . '`.');
		}
	}


	/** Replaces this node by the expression as the grammar of code reads it, which also holds inside the braces of a string. */
	private function replaceInCode(self $expression): void
	{
		if ($this->isBareName()) {
			$this->replaceBareName($expression);
		} else {
			$this->replaceParenthesized($expression);
		}
	}


	/**
	 * Whether the node is the name of a member or of a variable written without braces: `$o->$m`, `A::$m`, `$$m`;
	 * an expression naming a class constant always stands in braces.
	 */
	private function isBareName(): bool
	{
		$parent = $this->parent;
		return (
			$parent instanceof PropertyFetchNode
			|| $parent instanceof MethodCallNode
			|| $parent instanceof StaticMethodCallNode
			|| $parent instanceof StaticPropertyFetchNode
			|| $parent instanceof VariableNode
		)
			&& $parent->name === $this
			&& $parent->openBrace === null;
	}


	/** Replaces the bare name of a member or of a variable: a variable stands there bare, anything else in braces. */
	private function replaceBareName(self $expression): void
	{
		$parent = $this->parent;
		assert($parent instanceof PropertyFetchNode || $parent instanceof MethodCallNode || $parent instanceof StaticMethodCallNode || $parent instanceof StaticPropertyFetchNode || $parent instanceof VariableNode);
		$this->replaceWith($expression); // which refuses what the slot does not take before it moves anything
		if (!$expression instanceof VariableNode) {
			$parent->openBrace = Token::fromText('{');
			$parent->closeBrace = Token::fromText('}');
		}
	}


	/** Replaces this node by the expression, in parentheses where `ParenthesizedNode::isRedundant()` keeps them. */
	private function replaceParenthesized(self $expression): void
	{
		if ($expression instanceof ParenthesizedNode) {
			$this->replaceWith($expression);
			return;
		}

		$parent = $this->parent ?? throw new \LogicException('A node without a parent cannot be replaced.');
		if ($expression === $this || !self::canHold($parent, $this, ParenthesizedNode::class)) {
			$this->replaceWith($expression); // which refuses what the slot does not take before it moves anything
			return;
		}

		$parent->prepareValue($expression, $this); // the parentheses take the expression from where replaceWith() would take it
		// the trivia on the edges of the expression stand outside the parentheses, where they stay once those go
		[$leading, $trailing] = [$expression->leadingTrivia, $expression->trailingTrivia];
		$parenthesized = new ParenthesizedNode(Token::fromText('('), $expression->setEdgeTrivia([], []), Token::fromText(')'));
		$parenthesized->setEdgeTrivia($leading, $trailing);
		$this->replaceWith($parenthesized);
		if ($parenthesized->isRedundant()) {
			$parenthesized->replaceWith($expression);
		}
	}


	/**
	 * Whether the place of the child takes a node of the class, an expression in parentheses among them, which a slot
	 * typed narrower does not.
	 * @param  class-string<Node>  $class
	 */
	private static function canHold(Node $parent, Node $child, string $class): bool
	{
		$slot = $parent->findSlotOf($child);
		if ($parent instanceof NodeList || $slot === null) {
			return true;
		}

		$type = new \ReflectionProperty($parent, $slot)->getType();
		$types = $type instanceof \ReflectionUnionType ? $type->getTypes() : [$type];
		foreach ($types as $type) {
			if ($type instanceof \ReflectionNamedType && is_a($class, $type->getName(), allow_string: true)) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Writes the expression in place of this one standing in the part of a string, by the grammar of interpolation;
	 * see `replaceWithExpression()`, which has refused what the place cannot hold.
	 */
	private function replaceInString(self $expression, Node $part): void
	{
		if ($expression === $this) {
			return;
		}

		$parent = $this->parent ?? throw new \LogicException('A node without a parent cannot be replaced.');
		if ($part instanceof Scalar\InterpolationNode && !$part->openBrace->is(Token::CurlyOpen)) { // ${...}
			$this->replaceInCode($expression);

		} elseif ($part instanceof Scalar\InterpolationNode) { // {$...}
			if ($this->getFirstToken() !== $part->expression->getFirstToken()) {
				$this->replaceInCode($expression);
			} else { // what begins the braces is read as a variable
				$parent->prepareValue($expression, $this);
				$this->replaceWith($expression->setEdgeTrivia([], []));
			}

		} elseif ($part === $this) {
			$fits = $expression->fitsBareInString($this);
			$parent->prepareValue($expression, $this);
			$expression->setEdgeTrivia([], []);
			$this->replaceWith($fits ? $expression : new Scalar\InterpolationNode(new Token(Token::CurlyOpen, '{'), $expression, Token::fromText('}')));

		} elseif ($parent === $part && self::isPlainVariable($expression)) { // the variable or the offset of a bare part
			$parent->prepareValue($expression, $this);
			$this->replaceWith($expression->setEdgeTrivia([], []));

		} elseif ($part instanceof self) { // the bare part becomes `{$...}`, where the expression is written as there
			$braces = new Scalar\InterpolationNode(new Token(Token::CurlyOpen, '{'), new VariableNode(null, null, new Token(Token::Variable, '$_'), null), Token::fromText('}'));
			$part->replaceWith($braces); // a stand-in holds the braces until the part has left its place for them
			$braces->expression = $part;
			$this->replaceInString($expression, $braces);
		}

		self::markInterpolated($expression->findStringPart() ?? $expression, true);
	}


	/**
	 * Marks the trivia of every token of the node as standing in the interpolation of a string or outside one; outside,
	 * the parts of a string the node holds keep their marks.
	 */
	private static function markInterpolated(Node $node, bool $inInterpolation): void
	{
		$mark = fn(Trivia $trivia) => match (true) {
			$trivia->inInterpolation === $inInterpolation => $trivia,
			$inInterpolation => $trivia->withInterpolation(),
			default => $trivia->withoutInterpolation(),
		};
		foreach ($node->getChildren() as $child) {
			if ($child instanceof Node) {
				$isParts = $child instanceof PlainNodeList
					&& ($node instanceof Scalar\InterpolatedStringNode || $node instanceof Scalar\HeredocNode || $node instanceof ShellExecNode);
				if ($inInterpolation || !$isParts) {
					self::markInterpolated($child, $inInterpolation);
				}

				continue;
			}

			$leading = array_map($mark, $child->leadingTrivia);
			$trailing = array_map($mark, $child->trailingTrivia);
			if ($leading !== $child->leadingTrivia || $trailing !== $child->trailingTrivia) {
				$child->setLeadingTrivia($leading)->setTrailingTrivia($trailing);
			}
		}
	}


	/** The part of a string, a heredoc or a shell command the expression stands in, itself or an ancestor; null outside one. */
	private function findStringPart(): ?Node
	{
		for ($node = $this; ($parent = $node->parent) !== null; $node = $parent) {
			if (
				$parent instanceof PlainNodeList
				&& ($parent->parent instanceof Scalar\InterpolatedStringNode || $parent->parent instanceof Scalar\HeredocNode || $parent->parent instanceof ShellExecNode)
			) {
				return $node;
			}
		}

		return null;
	}


	private static function checkStandsInString(self $expression): void
	{
		if (!$expression->canStandInString()) {
			throw new \InvalidArgumentException('Expression ' . Helpers::formatCode($expression->text) . ' cannot be written inside a string, which takes a variable, an element, a property or a call reached from a variable.');
		}
	}


	/** Refuses braces around the part of a string where the text before it ends with a dollar, which would read `${`. */
	private static function checkBracesFit(Node $part): void
	{
		$list = $part->parent;
		$previous = $list instanceof NodeList ? ($list->getItems()[$list->indexOf($part) - 1] ?? null) : null;
		if ($previous instanceof Scalar\InterpolatedStringPartNode && preg_match('~(?<!\\\\)(?:\\\\\\\\)*\$$~D', $previous->token->text)) {
			throw new \InvalidArgumentException('Braces cannot be written right after the dollar of the text ' . Helpers::formatCode($previous->token->text) . ', which would read as `${`.');
		}
	}


	/**
	 * Whether the bare form of a string reads the expression standing in place of the part as it is: a plain variable
	 * the text after it does not continue, a plain variable with one offset that is a plain variable, and a plain
	 * variable with `->` or `?->` and a name the text after it does not continue, with no whitespace inside; a number
	 * or a string as an offset is written otherwise there, so such an element goes in braces.
	 */
	private function fitsBareInString(self $part): bool
	{
		$next = $part->parent instanceof NodeList ? ($part->parent->getItems()[$part->parent->indexOf($part) + 1] ?? null) : null;
		$after = $next instanceof Scalar\InterpolatedStringPartNode ? $next->token->text : '';
		return $this->text === implode('', $this->getTokenTexts())
			&& match (true) {
				self::isPlainVariable($this) => !preg_match('~^(\[|\??->[a-zA-Z_\x80-\xff]|[a-zA-Z0-9_\x80-\xff])~', $after),
				$this instanceof ArrayAccessNode => self::isPlainVariable($this->expression) && self::isPlainVariable($this->index),
				$this instanceof PropertyFetchNode => self::isPlainVariable($this->object) && $this->name instanceof IdentifierNode && !preg_match('~^[a-zA-Z0-9_\x80-\xff]~', $after),
				default => false,
			};
	}


	/** A variable written as `$name`, one token. */
	private static function isPlainVariable(?Node $node): bool
	{
		return $node instanceof VariableNode && $node->dollar === null && $node->name instanceof Token && $node->name->is(Token::Variable);
	}
}
