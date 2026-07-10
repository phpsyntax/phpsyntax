<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;

use PhpSyntax\Nodes\{ArgumentListNode, ArgumentNode, ArrayItemNode, DestructuringNode, ExpressionNode, IdentifierNode, NameNode, NodeList, StatementNode, TypeNode};
use PhpSyntax\Nodes\Expression\{ArrayAccessNode, ArrayNode, AssignmentNode, BinaryOpNode, CastNode, ClassConstantFetchNode, CombinedAssignmentNode, ConstantFetchNode, FunctionCallNode, MethodCallNode, NewNode, ParenthesizedNode, PropertyFetchNode, StaticMethodCallNode, StaticPropertyFetchNode, TernaryNode, UnaryOpNode, VariableNode};
use PhpSyntax\Nodes\Scalar\StringNode;
use PhpSyntax\Nodes\Statement\ForeachNode;
use function count, in_array, is_array, is_bool, is_float, is_int, is_scalar, is_string;


/**
 * Builds nodes out of text, nodes and values: everything but a whole file, which `Parser::parse()` reads. A node
 * given that stands in a tree is taken as a copy without the trivia on its edges, a detached one as it is with its
 * edges cleared; a value that is not a node is written by `value()`.
 * What is reached into or given to an operator stands in parentheses where it could not stand bare, by the answer of
 * `ParenthesizedNode::isRedundant()`, so the result is what the parser would read from its text.
 */
final class Builder
{
	private const UnaryOperators = ['!', '-', '+', '~', '@'];


	public function __construct(
		private readonly Parser $parser = new Parser,
	) {
	}


	/**
	 * The literal of a value: an integer, a float written so that it reads back as the same float, a boolean, null,
	 * a string, or an array on one line with its keys where they are not the sequence from 0; an expression is
	 * taken as it is. A negative number is the operator `-` on the literal, as the parser reads it.
	 */
	public function value(mixed $value): ExpressionNode
	{
		self::checkInputs($value);
		return match (true) {
			$value instanceof ExpressionNode => self::take($value),
			is_int($value), is_float($value) => $this->parser->parseFragment(ExpressionNode::class, var_export($value, return: true)),
			is_bool($value) => $this->parser->parseFragment(ExpressionNode::class, $value ? 'true' : 'false'),
			$value === null => $this->parser->parseFragment(ExpressionNode::class, 'null'),
			is_string($value) => StringNode::fromValue($value),
			is_array($value) => $this->writeArray($value),
			default => throw new \InvalidArgumentException('`' . get_debug_type($value) . '` is not an expression.'),
		};
	}


	/** @param array<mixed> $value */
	private function writeArray(array $value): ArrayNode
	{
		$items = $parts = [];
		$list = array_is_list($value);
		foreach ($value as $key => $item) {
			$i = count($parts);
			$parts["v$i"] = $this->value($item);
			if ($list) {
				$items[] = "\$v$i";
			} else {
				$parts["k$i"] = $this->value($key);
				$items[] = "\$k$i => \$v$i";
			}
		}

		return $this->compose(ArrayNode::class, '[' . implode(', ', $items) . ']', $parts);
	}


	/** A variable of the name, which is written with or without the dollar. */
	public function variable(string $name): VariableNode
	{
		$name = str_starts_with($name, '$') ? substr($name, 1) : $name;
		if (!preg_match('~^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$~D', $name)) {
			throw new \InvalidArgumentException(Helpers::formatCode($name) . ' is not a name of a variable.');
		}

		return $this->compose(VariableNode::class, '$' . $name, []);
	}


	/** A fetch of the constant of the name; `true`, `false` and `null` are literals, written by `value()`. */
	public function constant(string|NameNode $name): ConstantFetchNode
	{
		$name = is_string($name) ? NameNode::fromText($name) : $name;
		if ($name->form === NameForm::Unqualified && in_array(strtolower($name->text), ['true', 'false', 'null'], true)) {
			throw new \InvalidArgumentException(Helpers::formatCode($name->text) . ' is a literal, which `value()` writes.');
		}

		return new ConstantFetchNode(self::take($name));
	}


	/** A fetch of the property of the object, a name expression other than a variable written in braces. */
	public function propertyFetch(
		ExpressionNode $object,
		string|IdentifierNode|ExpressionNode $name,
		bool $nullsafe = false,
	): PropertyFetchNode
	{
		self::checkInputs($object, $name);
		$name = is_string($name) ? IdentifierNode::fromText($name) : $name;
		return $this->compose(PropertyFetchNode::class, '$o' . ($nullsafe ? '?->' : '->') . self::writeMember($name), ['o' => $object, 'n' => $name]);
	}


	/** A fetch of the static property of the class; the name is written with or without the dollar. */
	public function staticPropertyFetch(string|NameNode|ExpressionNode $class, string $name): StaticPropertyFetchNode
	{
		self::checkInputs($class);
		$class = is_string($class) ? NameNode::fromText($class) : $class;
		$variable = $this->variable($name);
		return $this->compose(StaticPropertyFetchNode::class, '$c::' . $variable->text, ['c' => $class]);
	}


	/** A fetch of the constant of the class, a name expression written in braces. */
	public function classConstantFetch(
		string|NameNode|ExpressionNode $class,
		string|IdentifierNode|ExpressionNode $name,
	): ClassConstantFetchNode
	{
		self::checkInputs($class, $name);
		$class = is_string($class) ? NameNode::fromText($class) : $class;
		$name = is_string($name) ? IdentifierNode::fromText($name) : $name;
		if ($name instanceof ExpressionNode) {
			return $this->compose(ClassConstantFetchNode::class, '$c::{$n}', ['c' => $class, 'n' => $name]);
		}

		$node = $this->compose(ClassConstantFetchNode::class, '$c::N', ['c' => $class]);
		$node->name = self::take($name);
		return $node;
	}


	/** An access to the element of the array at the index, or `$array[]` where none is given; the literal null as an index is `value(null)`. */
	public function arrayAccess(ExpressionNode $array, mixed $index = null): ArrayAccessNode
	{
		self::checkInputs($array, $index);
		return $index === null
			? $this->compose(ArrayAccessNode::class, '$a[]', ['a' => $array])
			: $this->compose(ArrayAccessNode::class, '$a[$i]', ['a' => $array, 'i' => $this->value($index)]);
	}


	/**
	 * A call of the function of the name, or of the expression.
	 * @param  array<mixed>|ArgumentListNode  $arguments  see `arguments()`
	 */
	public function call(string|NameNode|ExpressionNode $name, array|ArgumentListNode $arguments = []): FunctionCallNode
	{
		self::checkInputs($name, $arguments);
		$name = is_string($name) ? NameNode::fromText($name) : $name;
		$node = $this->compose(FunctionCallNode::class, '$n()', ['n' => $name]);
		$node->arguments = $this->arguments($arguments);
		return $node;
	}


	/**
	 * A call of the method on the object, a name expression other than a variable written in braces.
	 * @param  array<mixed>|ArgumentListNode  $arguments  see `arguments()`
	 */
	public function methodCall(
		ExpressionNode $object,
		string|IdentifierNode|ExpressionNode $name,
		array|ArgumentListNode $arguments = [],
		bool $nullsafe = false,
	): MethodCallNode
	{
		self::checkInputs($object, $name, $arguments);
		$name = is_string($name) ? IdentifierNode::fromText($name) : $name;
		$node = $this->compose(MethodCallNode::class, '$o' . ($nullsafe ? '?->' : '->') . self::writeMember($name) . '()', ['o' => $object, 'n' => $name]);
		$node->arguments = $this->arguments($arguments);
		return $node;
	}


	/**
	 * A call of the static method of the class, a name expression other than a variable written in braces.
	 * @param  array<mixed>|ArgumentListNode  $arguments  see `arguments()`
	 */
	public function staticMethodCall(
		string|NameNode|ExpressionNode $class,
		string|IdentifierNode|ExpressionNode $name,
		array|ArgumentListNode $arguments = [],
	): StaticMethodCallNode
	{
		self::checkInputs($class, $name, $arguments);
		$class = is_string($class) ? NameNode::fromText($class) : $class;
		$name = is_string($name) ? IdentifierNode::fromText($name) : $name;
		$node = $this->compose(StaticMethodCallNode::class, '$c::' . self::writeMember($name) . '()', ['c' => $class, 'n' => $name]);
		$node->arguments = $this->arguments($arguments);
		return $node;
	}


	/**
	 * An instantiation of the class; no list of arguments is written where none is given.
	 * @param  array<mixed>|ArgumentListNode  $arguments  see `arguments()`
	 */
	public function new(string|NameNode|ExpressionNode $class, array|ArgumentListNode $arguments = []): NewNode
	{
		self::checkInputs($class, $arguments);
		$class = is_string($class) ? NameNode::fromText($class) : $class;
		$node = $this->compose(NewNode::class, 'new $c', ['c' => $class]);
		if ($arguments !== []) {
			$node->arguments = $this->arguments($arguments);
		}

		return $node;
	}


	/**
	 * A list of arguments on one line: values or expressions, an `ArgumentNode` as it is, and under a string key
	 * an argument with that name; a list given is taken as it is.
	 * @param  array<mixed>|ArgumentListNode  $arguments
	 */
	public function arguments(array|ArgumentListNode $arguments): ArgumentListNode
	{
		self::checkInputs($arguments);
		if ($arguments instanceof ArgumentListNode) {
			return self::take($arguments);
		}

		foreach ($arguments as $key => $argument) {
			if (is_string($key) && $argument instanceof ArgumentNode) {
				throw new \InvalidArgumentException("Argument `$key` is an `ArgumentNode`, which carries its own name.");
			} elseif (is_string($key)) {
				IdentifierNode::fromText($key);
			}
		}

		$items = $parts = $given = [];
		foreach ($arguments as $key => $argument) {
			$i = count($items);
			if ($argument instanceof ArgumentNode) {
				$given[$i] = $argument;
				$items[] = 'null';
				continue;
			}

			$parts["a$i"] = $this->value($argument);
			$items[] = (is_string($key) ? "$key: " : '') . "\$a$i";
		}

		$call = $this->compose(FunctionCallNode::class, 'f(' . implode(', ', $items) . ')', $parts);
		$list = $call->arguments;
		foreach ($given as $i => $argument) {
			$list->items[$i]->replaceWith(self::take($argument));
		}

		$call->dismantle();
		return $list;
	}


	/** The binary operation, an operand in parentheses where its side of the operator asks. */
	public function binary(mixed $left, string $operator, mixed $right): BinaryOpNode
	{
		self::checkInputs($left, $right);
		$node = $this->parseOperator(BinaryOpNode::class, "\$l $operator \$r", $operator, Helpers::formatCode($operator) . ' is not a binary operator.');
		return $this->compose(BinaryOpNode::class, $node, ['l' => $this->value($left), 'r' => $this->value($right)]);
	}


	/** The operation of `!`, `-`, `+`, `~` or `@`. */
	public function unary(string $operator, mixed $operand): UnaryOpNode
	{
		self::checkInputs($operand);
		if (!in_array($operator, self::UnaryOperators, true)) {
			throw new \InvalidArgumentException(Helpers::formatCode($operator) . ' is not a unary operator.');
		}

		return $this->compose(UnaryOpNode::class, $operator . '$e', ['e' => $this->value($operand)]);
	}


	/** The cast to the type, written as `int` for `(int)`. */
	public function cast(string $type, mixed $operand): CastNode
	{
		self::checkInputs($operand);
		$refusal = Helpers::formatCode($type) . ' is not a type of a cast.';
		if (!preg_match('~^[a-zA-Z]+$~D', $type)) {
			throw new \InvalidArgumentException($refusal);
		}

		$node = $this->parseOperator(CastNode::class, "($type) \$e", "($type)", $refusal);
		return $this->compose(CastNode::class, $node, ['e' => $this->value($operand)]);
	}


	/** The ternary operation; a null `$then` is the literal, the short `?:` is `shortTernary()`. */
	public function ternary(mixed $condition, mixed $then, mixed $else): TernaryNode
	{
		self::checkInputs($condition, $then, $else);
		return $this->compose(TernaryNode::class, '$c ? $t : $e', ['c' => $this->value($condition), 't' => $this->value($then), 'e' => $this->value($else)]);
	}


	/** The short ternary operation `?:`, which gives the condition itself where it is true. */
	public function shortTernary(mixed $condition, mixed $else): TernaryNode
	{
		self::checkInputs($condition, $else);
		return $this->compose(TernaryNode::class, '$c ?: $e', ['c' => $this->value($condition), 'e' => $this->value($else)]);
	}


	/**
	 * The assignment of the expression to the target, a short array written as the target destructuring as it does
	 * in the code.
	 * @throws \InvalidArgumentException  for a target nothing can be assigned to
	 */
	public function assign(ExpressionNode|DestructuringNode $target, mixed $expression): AssignmentNode
	{
		self::checkInputs($target, $expression);
		$destructuring = $target instanceof DestructuringNode || ($target instanceof ArrayNode && $target->arrayKeyword === null);
		if (!$destructuring && !$target->isWritable()) {
			throw new \InvalidArgumentException(Helpers::formatCode($target->text) . ' is no place to assign to.');
		}

		$node = $this->compose(AssignmentNode::class, '$t = $e', ['t' => $target, 'e' => $this->value($expression)]);
		if ($destructuring) {
			$node->target = DestructuringNode::destructure($node->target);
		}

		return $node;
	}


	/**
	 * The combined assignment of the expression to the target.
	 * @throws \InvalidArgumentException  for what is no combined assignment operator, and for a target nothing can be assigned to
	 */
	public function combinedAssign(ExpressionNode $target, string $operator, mixed $expression): CombinedAssignmentNode
	{
		self::checkInputs($target, $expression);
		$node = $this->parseOperator(CombinedAssignmentNode::class, "\$t $operator \$e", $operator, Helpers::formatCode($operator) . ' is not a combined assignment operator.');
		if (!$target->isWritable()) {
			throw new \InvalidArgumentException(Helpers::formatCode($target->text) . ' is no place to assign to.');
		}

		return $this->compose(CombinedAssignmentNode::class, $node, ['t' => $target, 'e' => $this->value($expression)]);
	}


	/** The expression in parentheses. */
	public function parenthesize(mixed $expression): ParenthesizedNode
	{
		self::checkInputs($expression);
		return $this->compose(ParenthesizedNode::class, '($e)', ['e' => $this->value($expression)]);
	}


	/**
	 * The expression the code is read as. Every variable named as a placeholder (`$name` for `name: $node`) is
	 * replaced by the node given, in parentheses where its place asks.
	 * @throws ParseException
	 */
	public function expression(string $code, ExpressionNode|DestructuringNode ...$placeholders): ExpressionNode
	{
		return $this->fragment(ExpressionNode::class, $code, ...$placeholders);
	}


	/**
	 * The statement the code is read as, its placeholders replaced as `expression()` does.
	 * @throws ParseException
	 */
	public function statement(string $code, ExpressionNode|DestructuringNode ...$placeholders): StatementNode
	{
		return $this->fragment(StatementNode::class, $code, ...$placeholders);
	}


	/** @throws ParseException */
	public function type(string $code): TypeNode
	{
		return $this->fragment(TypeNode::class, $code);
	}


	/** @throws ParseException */
	public function name(string $code): NameNode
	{
		return $this->fragment(NameNode::class, $code);
	}


	/**
	 * The node of the class the code is read as, parsed inside the code such a node stands in: an expression, a
	 * statement, a type, a name, a member, a parameter, an argument, an array item, an import item and the other
	 * kinds of items, or a class deriving from one of them. It comes back detached, without original positions and
	 * with empty trivia on its edges, and code that leaves something over is refused. Its placeholders are replaced
	 * as `expression()` does.
	 * @template T of Node
	 * @param  class-string<T>  $class
	 * @return T
	 * @throws ParseException
	 */
	public function fragment(string $class, string $code, ExpressionNode|DestructuringNode ...$placeholders): Node
	{
		$parts = [];
		foreach ($placeholders as $name => $placeholder) {
			$parts[is_string($name) ? $name : throw new \InvalidArgumentException('A placeholder is given by its name, as `name: $node` for `$name`.')] = $placeholder;
		}

		self::checkInputs(...array_values($parts));
		$node = $this->fill($this->parser->parseFragment($class, $code), $parts);
		return $node instanceof $class ? $node : throw new \LogicException;
	}


	/**
	 * The expression the template is read as, its placeholders replaced.
	 * @template T of ExpressionNode
	 * @param  class-string<T>  $class
	 * @param  array<string, Node>  $parts
	 * @return T
	 */
	private function compose(string $class, string|ExpressionNode $template, array $parts): ExpressionNode
	{
		$node = $this->fill(is_string($template) ? $this->parser->parseFragment(ExpressionNode::class, $template) : $template, $parts);
		return $node instanceof $class ? $node : throw new \LogicException;
	}


	/**
	 * The expression of an operator given as text, refused as what it is not where the template does not read as
	 * the class with that operator.
	 * @template T of BinaryOpNode|CombinedAssignmentNode|CastNode
	 * @param  class-string<T>  $class
	 * @return T
	 */
	private function parseOperator(string $class, string $template, string $operator, string $refusal): ExpressionNode
	{
		try {
			$node = $this->parser->parseFragment(ExpressionNode::class, $template);
		} catch (ParseException) {
			$node = null;
		}

		return $node instanceof $class && $node->operator->text === $operator
			? $node
			: throw new \InvalidArgumentException($refusal);
	}


	/** How a member name is written after `->` or `::`: an expression other than a variable stands in braces. */
	private static function writeMember(IdentifierNode|ExpressionNode $name): string
	{
		return $name instanceof ExpressionNode && !$name instanceof VariableNode ? '{$n}' : '$n';
	}


	/**
	 * Replaces the variables of the template named as the parts by the parts: an expression the way
	 * `replaceWithExpression()` does, anything else the way `replaceWith()` does, a part used twice by a copy the
	 * second time. Everything is checked before the first part is taken, the variables are found before any of
	 * them is replaced, so a variable inside a part is never taken for a placeholder.
	 * @param  array<string, Node>  $parts
	 * @return Node  the part itself where the whole template is its placeholder
	 */
	private function fill(Node $template, array $parts): Node
	{
		$occurrences = [];
		foreach ([$template, ...$template->find(VariableNode::class)] as $variable) {
			if ($variable instanceof VariableNode && isset($parts[$variable->plainName ?? ''])) {
				$occurrences[] = $variable;
			}
		}

		$uses = [];
		foreach ($occurrences as $variable) {
			$name = (string) $variable->plainName;
			$uses[$name] = ($uses[$name] ?? 0) + 1;
			self::checkPlace($variable, $parts[$name]);
		}

		foreach (array_keys($parts) as $name) {
			if (!isset($uses[$name])) {
				throw new \InvalidArgumentException("Placeholder `\$$name` does not stand in the template.");
			}
		}

		$copies = [];
		foreach ($parts as $name => $part) {
			$part = self::take($part);
			$copies[$name] = [$part];
			for ($i = 1; $i < $uses[$name]; $i++) {
				$copies[$name][] = clone $part;
			}
		}

		$result = $template;
		foreach ($occurrences as $variable) {
			$part = array_shift($copies[(string) $variable->plainName]) ?? throw new \LogicException;
			if ($variable === $template) {
				$result = $part;
			} elseif ($part instanceof ExpressionNode) {
				$variable->replaceWithExpression($part);
			} else {
				$variable->replaceWith($part);
			}
		}

		return $result;
	}


	/**
	 * Refuses a part that cannot stand where the placeholder stands, before anything moves: by the type of the slot,
	 * and an expression by everything `replaceWithExpression()` refuses, a place in a string among it.
	 */
	private static function checkPlace(VariableNode $variable, Node $part): void
	{
		$parent = $variable->parent;
		if ($part instanceof DestructuringNode && !self::isTarget($variable)) {
			throw new \InvalidArgumentException("Placeholder `\$$variable->plainName` is a destructuring, which stands only where a target is written.");
		} elseif ($parent === null) {
			return;
		}

		if (!$parent instanceof NodeList) {
			$slot = (string) $parent->findSlotOf($variable);
			$type = new \ReflectionProperty($parent, $slot)->getType();
			$types = $type instanceof \ReflectionUnionType ? $type->getTypes() : [$type];
			$fits = array_any($types, fn(?\ReflectionType $type) => $type instanceof \ReflectionNamedType && is_a($part, $type->getName()));
			if (!$fits) {
				throw new \InvalidArgumentException("Placeholder `\$$variable->plainName` stands in the slot `$slot` of `" . $parent::class . '`, which does not take `' . $part::class . '`.');
			}
		}

		if ($part instanceof ExpressionNode) {
			$variable->checkPlaceHolds($part);
		}
	}


	/** Whether the variable stands where a target is written: the left of `=`, the value of a foreach or of an item of a destructuring. */
	private static function isTarget(VariableNode $variable): bool
	{
		$parent = $variable->parent;
		return ($parent instanceof AssignmentNode && $parent->target === $variable)
			|| ($parent instanceof ForeachNode && $parent->value === $variable)
			|| ($parent instanceof ArrayItemNode && $parent->value === $variable && $parent->parent?->parent instanceof DestructuringNode);
	}


	/**
	 * A node standing in a tree as a copy without the trivia on its edges, a detached one as it is with its edges cleared.
	 * @template T of Node
	 * @param  T  $node
	 * @return T
	 */
	private static function take(Node $node): Node
	{
		return $node->parent !== null ? $node->withoutEdgeTrivia() : $node->setEdgeTrivia([], []);
	}


	/**
	 * Refuses what a value cannot be written as and a detached node given twice, which would be taken from the place
	 * the builder has just put it in, before anything moves; a node standing in a tree comes in as a copy each time.
	 */
	private static function checkInputs(mixed ...$values): void
	{
		$detached = [];
		$collect = function (mixed $value) use (&$collect, &$detached): void {
			if ($value instanceof Node) {
				if ($value->parent === null) {
					if (in_array($value, $detached, true)) {
						throw new \LogicException('A node cannot be two parts of the node the builder builds; a copy comes from `withoutEdgeTrivia()`.');
					}

					$detached[] = $value;
				}
			} elseif (is_array($value)) {
				array_map($collect, $value);
			} elseif ($value !== null && !is_scalar($value)) {
				throw new \InvalidArgumentException('Value of type `' . get_debug_type($value) . '` cannot be written as an expression.');
			}
		};
		array_map($collect, $values);
	}
}
