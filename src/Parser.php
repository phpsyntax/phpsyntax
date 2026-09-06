<?php declare(strict_types=1);

namespace PhpSyntax;

use PhpSyntax\Lexer\Lexer;
use PhpSyntax\Nodes\ArgumentNode;
use PhpSyntax\Nodes\ArrayItemNode;
use PhpSyntax\Nodes\AttributeGroupNode;
use PhpSyntax\Nodes\CatchNode;
use PhpSyntax\Nodes\ClosureUseNode;
use PhpSyntax\Nodes\ConstItemNode;
use PhpSyntax\Nodes\ElseIfNode;
use PhpSyntax\Nodes\EmptyArrayItemNode;
use PhpSyntax\Nodes\Expression\ArrayNode;
use PhpSyntax\Nodes\Expression\ConstantFetchNode;
use PhpSyntax\Nodes\Expression\ListNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\MatchArmNode;
use PhpSyntax\Nodes\Member\PropertyHookNode;
use PhpSyntax\Nodes\MemberNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\ParameterNode;
use PhpSyntax\Nodes\Scalar\BooleanNode;
use PhpSyntax\Nodes\Scalar\IntegerNode;
use PhpSyntax\Nodes\Scalar\NullNode;
use PhpSyntax\Nodes\Scalar\UnquotedStringNode;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\Statement\HaltCompilerNode;
use PhpSyntax\Nodes\Statement\NamespaceNode;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Nodes\StaticVariableNode;
use PhpSyntax\Nodes\TypeNode;
use PhpSyntax\Nodes\UseItemNode;
use function array_slice, count, ord, strlen;


/**
 * LALR(1) parser over the generated tables; builds the concrete syntax tree.
 * Based on works by Nikita Popov, Moriyoshi Koizumi and Masato Bito.
 */
final class Parser
{
	use ParserData;

	private const SymbolNone = -1;

	/** @var array<class-string, array{string, string}>  the code a fragment of the kind is parsed in */
	private const Wrappers = [
		ExpressionNode::class => ['<?php ', "\n;"],
		StatementNode::class => ['<?php ', ''],
		TypeNode::class => ['<?php function f(): ', "\n{}"],
		NameNode::class => ['<?php ', "\n::class;"],
		MemberNode::class => ['<?php class C { ', "\n}"],
		ParameterNode::class => ['<?php function f(', "\n) {}"],
		ArgumentNode::class => ['<?php f(', "\n);"],
		ArrayItemNode::class => ['<?php [', "\n];"],
		UseItemNode::class => ['<?php use ', "\n;"],
		MatchArmNode::class => ['<?php match (0) { ', "\n};"],
		AttributeGroupNode::class => ['<?php ', "\nfunction f() {}"],
		CatchNode::class => ['<?php try {} ', "\n"],
		ElseIfNode::class => ['<?php if (0) {} ', "\n"],
		ClosureUseNode::class => ['<?php function () use (', "\n) {};"],
		StaticVariableNode::class => ['<?php static ', "\n;"],
		ConstItemNode::class => ['<?php const ', "\n;"],
		PropertyHookNode::class => ['<?php class C { public $p { ', "\n} }"],
	];

	/** the source of the file being parsed, which an error reports its place in */
	private string $code = '';

	/** @var list<Token> */
	private array $tokens = [];
	private int $position = 0;

	/** @var array<int, mixed>  semantic value stack: tokens, nodes, and arrays of slot values of a node under construction */
	private array $semStack = [];

	/** result of the last reduction */
	private mixed $semValue = null;


	public function __construct(
		private readonly Lexer $lexer = new Lexer,
	) {
	}


	/** @throws ParseException */
	public function parse(string $code): FileNode
	{
		return $this->parseFile($code, withPositions: true);
	}


	/**
	 * Parses a fragment into a detached node of the class, without original positions and with empty trivia
	 * on its edges; it is parsed inside the code such a node stands in, which the wrappers hold.
	 * @template T of Node
	 * @param  class-string<T>  $class
	 * @return T
	 * @throws ParseException
	 */
	public function parseFragment(string $class, string $code): Node
	{
		[$prefix, $suffix] = self::findWrapper($class)
			?? throw new \InvalidArgumentException("There is no code a node of '$class' could be parsed in.");
		$node = $this->parseFile($prefix . $code . $suffix, withPositions: false)->findFirst($class);
		return $node !== null && $this->isWholeFragment($node, $prefix, $suffix)
			? $this->detach($node)
			: throw new ParseException('The code is not a single ' . self::describe($class) . '.');
	}


	/** @throws ParseException */
	public function parseExpression(string $code): ExpressionNode
	{
		return $this->parseFragment(ExpressionNode::class, $code);
	}


	/** @throws ParseException */
	public function parseStatement(string $code): StatementNode
	{
		return $this->parseFragment(StatementNode::class, $code);
	}


	/** @throws ParseException */
	public function parseType(string $code): TypeNode
	{
		return $this->parseFragment(TypeNode::class, $code);
	}


	/** @throws ParseException */
	public function parseName(string $code): NameNode
	{
		return $this->parseFragment(NameNode::class, $code);
	}


	/**
	 * The code a node of the class stands in, split where the fragment goes; the class may be any of the
	 * kinds the wrappers name, or one deriving from it.
	 * @param  class-string  $class
	 * @return ?array{string, string}
	 */
	private static function findWrapper(string $class): ?array
	{
		foreach (self::Wrappers as $kind => $wrapper) {
			if (is_a($class, $kind, allow_string: true)) {
				return $wrapper;
			}
		}

		return null;
	}


	/**
	 * Whether the node covers the whole fragment and not just its beginning, as it does for "$a, $b" given
	 * as one parameter: it has to reach from the first token after the prefix to the last before the suffix.
	 */
	private function isWholeFragment(Node $node, string $prefix, string $suffix): bool
	{
		$tokens = $this->tokens; // of the file just parsed, the end of file token last
		$before = count($this->lexer->tokenize($prefix, withPositions: false)) - 1;
		$after = count($this->lexer->tokenize('<?php ' . $suffix, withPositions: false)) - 1;
		return $node->getFirstToken() === ($tokens[$before] ?? null)
			&& $node->getLastToken() === ($tokens[count($tokens) - 2 - $after] ?? null);
	}


	/** The kind of node in words, the way the class names it: ArrayItemNode is an array item. */
	private static function describe(string $class): string
	{
		$name = substr($class, (int) strrpos($class, '\\') + 1, -strlen('Node'));
		return strtolower((string) preg_replace('~(?<!^)[A-Z]~', ' $0', $name));
	}


	private function parseFile(string $code, bool $withPositions): FileNode
	{
		$this->code = $code;
		$this->tokens = $this->lexer->tokenize($code, $withPositions);
		$this->position = 0;
		$stmts = $this->run();
		if (!$stmts instanceof NodeList) {
			throw new \LogicException('The start production must yield a list of statements.');
		}

		$eof = $this->tokens[count($this->tokens) - 1];
		$data = $this->tokens[count($this->tokens) - 2] ?? null;
		if ($data?->kind === TokenKind::HaltCompilerData) {
			$halt = $stmts->items[count($stmts->items) - 1] ?? null;
			if (!$halt instanceof HaltCompilerNode) {
				throw new \LogicException('__halt_compiler() data without the halt statement.');
			}

			$halt->data = $data;
		}

		/** @var NodeList<StatementNode> $stmts */
		return new FileNode($this->nestNamespaces($stmts), $eof);
	}


	/**
	 * Takes the node out of the helper tree it was parsed in and clears the trivia on its edges.
	 * @template T of Node
	 * @param  T  $node
	 * @return T
	 */
	private function detach(Node $node): Node
	{
		$node->attachTo(null);
		if ($first = $node->getFirstToken()) {
			$first->setLeadingTrivia([]);
		}

		if ($last = $node->getLastToken()) {
			$last->setTrailingTrivia([]);
		}

		return $node;
	}


	/**
	 * Moves the statements following "namespace A;" into that namespace, so that every namespace has its
	 * statements as children; only __halt_compiler() stays outside.
	 * @param  NodeList<StatementNode>  $stmts
	 * @return NodeList<StatementNode>
	 */
	private function nestNamespaces(NodeList $stmts): NodeList
	{
		$hasUnbraced = false;
		foreach ($stmts->items as $stmt) {
			$hasUnbraced = $hasUnbraced || ($stmt instanceof NamespaceNode && $stmt->semicolon);
		}

		if (!$hasUnbraced) {
			return $stmts;
		}

		$result = new NodeList;
		$current = null;
		foreach ($stmts->items as $stmt) {
			$stmt->attachTo(null);
			if ($stmt instanceof NamespaceNode) {
				$current = $stmt->semicolon ? $stmt : null;
				$result->append($stmt);
			} elseif ($stmt instanceof HaltCompilerNode) {
				$current = null;
				$result->append($stmt);
			} elseif ($current) {
				$current->statements->append($stmt);
			} else {
				$result->append($stmt);
			}
		}

		return $result;
	}


	private function run(): Node|Token|null
	{
		$symbol = self::SymbolNone;
		$token = $this->tokens[$this->position];
		$state = 0;
		$stateStack = [$state];
		$stackPos = 0;
		$this->semStack = [];

		do {
			if (self::ActionBase[$state] === 0) {
				$rule = self::ActionDefault[$state];
			} else {
				if ($symbol === self::SymbolNone) {
					$token = $this->tokens[$this->position];
					$symbol = self::TokenToSymbol[match ($token->kind) {
						TokenKind::CloseTag => ord(';'),
						TokenKind::OpenTagWithEcho => TokenKind::Echo,
						TokenKind::HaltCompilerData => TokenKind::EndOfFile,
						default => $token->kind,
					}];
				}

				$idx = self::ActionBase[$state] + $symbol;
				if (
					(($idx >= 0 && $idx < count(self::Action) && self::ActionCheck[$idx] === $symbol)
						|| ($state < self::Yy2Tblstate
							&& ($idx = self::ActionBase[$state + self::NumNonLeafStates] + $symbol) >= 0
							&& $idx < count(self::Action) && self::ActionCheck[$idx] === $symbol))
					&& ($action = self::Action[$idx]) !== self::DefaultAction
				) {
					/*
					>= numNonLeafStates: shift and reduce
					> 0: shift
					= 0: accept
					< 0: reduce
					= -YYUNEXPECTED: error
					*/
					if ($action > 0) { // shift
						++$stackPos;
						$stateStack[$stackPos] = $state = $action;
						$this->semStack[$stackPos] = $token;
						if ($token->kind !== TokenKind::EndOfFile && $token->kind !== TokenKind::HaltCompilerData) {
							$this->position++;
						}

						$symbol = self::SymbolNone;
						if ($action < self::NumNonLeafStates) {
							continue;
						}

						$rule = $action - self::NumNonLeafStates; // shift-and-reduce
					} else {
						$rule = -$action;
					}
				} else {
					$rule = self::ActionDefault[$state];
				}
			}

			do {
				if ($rule === 0) { // accept
					return $this->semValue;

				} elseif ($rule !== self::UnexpectedTokenRule) { // reduce
					$this->reduce($rule, $stackPos);

					// goto - shift nonterminal
					$stackPos -= self::RuleToLength[$rule];
					$nonTerminal = self::RuleToNonTerminal[$rule];
					$idx = self::GotoBase[$nonTerminal] + $stateStack[$stackPos];
					$state = $idx >= 0 && $idx < count(self::Goto) && self::GotoCheck[$idx] === $nonTerminal
						? self::Goto[$idx]
						: self::GotoDefault[$nonTerminal];

					++$stackPos;
					$stateStack[$stackPos] = $state;
					$this->semStack[$stackPos] = $this->semValue;

				} else {
					throw $this->createUnexpectedTokenException($state, $token);
				}

				if ($state < self::NumNonLeafStates) {
					break;
				}

				$rule = $state - self::NumNonLeafStates; // shift-and-reduce
			} while (true);
		} while (true);
	}


	/**
	 * The grammar parses "[1,]" and "[]" with an empty item after the last comma; that item is really
	 * a trailing separator, or nothing at all.
	 * @param  SeparatedNodeList<ArrayItemNode|EmptyArrayItemNode>  $items
	 * @return SeparatedNodeList<ArrayItemNode|EmptyArrayItemNode>
	 */
	protected function finishArrayItems(SeparatedNodeList $items): SeparatedNodeList
	{
		$last = $items->items[count($items->items) - 1];
		if ($last instanceof EmptyArrayItemNode) {
			foreach ($items->getChildren() as $child) { // the new list adopts what the old one held
				$child->attachTo(null);
			}

			return new SeparatedNodeList(array_slice($items->items, 0, -1), $items->separators);
		}

		return $items;
	}


	/**
	 * An offset inside an interpolated string is an integer only when it is written the way PHP writes one
	 * ("$a[0]"); anything else is a string offset, leading zeros and all ("$a[01]" reads the key '01').
	 */
	protected function offsetNumber(Token $token): IntegerNode|UnquotedStringNode
	{
		return $token->text === (string) (int) $token->text
			? new IntegerNode($token)
			: new UnquotedStringNode($token);
	}


	/**
	 * The destructuring a target is: a short array written where a place is assigned to means the same as
	 * list(...) and becomes one, its own items included, however deep they nest. Anything else stands as it is,
	 * a long array among it, which PHP does not take for a target.
	 */
	protected function toDestructuring(ExpressionNode|ListNode $target): ExpressionNode|ListNode
	{
		if ($target instanceof ListNode) {
			$this->destructureItems($target->items);
			return $target;

		} elseif (!$target instanceof ArrayNode || $target->arrayKeyword !== null) {
			return $target;
		}

		[$open, $items, $close] = [$target->openDelimiter, $target->items, $target->closeDelimiter];
		foreach ($target->getChildren() as $child) {
			$child->attachTo(null); // the array is dropped and its parts go on
		}

		$this->destructureItems($items);
		return new ListNode(null, $open, $items, $close);
	}


	/** @param SeparatedNodeList<ArrayItemNode|EmptyArrayItemNode> $items */
	private function destructureItems(SeparatedNodeList $items): void
	{
		foreach ($items->getItems() as $item) {
			if ($item instanceof ArrayItemNode && ($value = $this->toDestructuring($item->value)) !== $item->value) {
				$item->value = $value;
			}
		}
	}


	/**
	 * Takes a variable apart into the slots of the node that holds a name written as one; the variable is
	 * dropped and its children go on.
	 * @return array{?Token, ?Token, Token|ExpressionNode, ?Token}
	 */
	protected function unwrapVariable(VariableNode $var): array
	{
		foreach ($var->getChildren() as $child) {
			$child->attachTo(null);
		}

		return [$var->dollar, $var->openBrace, $var->name, $var->closeBrace];
	}


	/**
	 * true, false and null are the value only where they stand alone or behind a backslash;
	 * a qualified or relative name of the same spelling is an ordinary constant.
	 */
	protected function makeConstant(NameNode $name): BooleanNode|NullNode|ConstantFetchNode
	{
		$literal = match (strtolower(ltrim($name->token->text, '\\'))) {
			'true', 'false' => BooleanNode::class,
			'null' => NullNode::class,
			default => null,
		};
		if ($literal === null) {
			return new ConstantFetchNode($name);
		}

		$token = $name->token;
		$token->attachTo(null);
		return new $literal($token);
	}


	/**
	 * A production without an action passes its single symbol through, an empty one yields null;
	 * a longer one must have an action, otherwise tokens would drop out of the tree.
	 */
	protected function reduceDefault(int $rule, int $pos): void
	{
		$this->semValue = match (self::RuleToLength[$rule]) {
			0 => null,
			1 => $this->semStack[$pos],
			default => throw new \LogicException("Grammar rule $rule has no semantic action."),
		};
	}


	private function createUnexpectedTokenException(int $state, Token $token): ParseException
	{
		$message = $token->kind === TokenKind::EndOfFile
			? 'Unexpected end of file'
			: "Unexpected '$token->text'";
		if ($expected = $this->getExpectedTokens($state)) {
			$last = array_pop($expected);
			$message .= ', expecting ' . ($expected ? implode(', ', $expected) . ' or ' : '') . $last;
		}

		return new ParseException($message, $token->originalLine, $token->originalOffset, $this->code);
	}


	/**
	 * Names of tokens acceptable in the state, or [] when there are too many to be helpful.
	 * @return list<string>
	 */
	private function getExpectedTokens(int $state): array
	{
		$expected = [];
		$base = self::ActionBase[$state];
		foreach (self::SymbolToName as $symbol => $name) {
			$idx = $base + $symbol;
			if (
				(($idx >= 0 && $idx < count(self::Action) && self::ActionCheck[$idx] === $symbol)
					|| ($state < self::Yy2Tblstate
						&& ($idx = self::ActionBase[$state + self::NumNonLeafStates] + $symbol) >= 0
						&& $idx < count(self::Action) && self::ActionCheck[$idx] === $symbol))
				&& self::Action[$idx] !== self::UnexpectedTokenRule
				&& self::Action[$idx] !== self::DefaultAction
				&& $symbol !== 0
			) {
				if (count($expected) === 4) {
					return [];
				}

				$expected[] = $name;
			}
		}

		return $expected;
	}
}
