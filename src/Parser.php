<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;

use PhpSyntax\Lexer\Lexer;
use PhpSyntax\Nodes\{ArrayItemNode, EmptyArrayItemNode, ExpressionNode, FileNode, NameNode, NodeList, SeparatedNodeList, StatementNode};
use PhpSyntax\Nodes\Expression\{ArrayNode, ConstantFetchNode, ListNode, VariableNode};
use PhpSyntax\Nodes\Scalar\{BooleanNode, IntegerNode, NullNode, UnquotedStringNode};
use PhpSyntax\Nodes\Statement\{HaltCompilerNode, NamespaceNode};
use function count, ord;


/**
 * LALR(1) parser over the generated tables; builds the concrete syntax tree.
 * Based on works by Nikita Popov, Moriyoshi Koizumi and Masato Bito.
 */
final class Parser
{
	use ParserData;

	private const SymbolNone = -1;

	/** @var array<string, string>  how an error names an expected token, the way PHP names it; a keyword is named by itself */
	private const TokenNames = [
		'EOF' => 'end of file',
		'T_LNUMBER' => 'integer',
		'T_DNUMBER' => 'floating-point number',
		'T_STRING' => 'identifier',
		'T_STRING_VARNAME' => 'variable name',
		'T_VARIABLE' => 'variable',
		'T_NUM_STRING' => 'number',
		'T_INLINE_HTML' => 'inline HTML',
		'T_ENCAPSED_AND_WHITESPACE' => 'string content',
		'T_CONSTANT_ENCAPSED_STRING' => 'quoted string',
		'T_START_HEREDOC' => 'heredoc start',
		'T_END_HEREDOC' => 'heredoc end',
		'T_NAME_FULLY_QUALIFIED' => 'fully qualified name',
		'T_NAME_QUALIFIED' => 'namespaced name',
		'T_NAME_RELATIVE' => 'namespace-relative name',
		'T_VOID_CAST' => "'(void)'",
		'T_INT_CAST' => "'(int)'",
		'T_DOUBLE_CAST' => "'(float)'",
		'T_STRING_CAST' => "'(string)'",
		'T_ARRAY_CAST' => "'(array)'",
		'T_OBJECT_CAST' => "'(object)'",
		'T_BOOL_CAST' => "'(bool)'",
		'T_UNSET_CAST' => "'(unset)'",
		'T_LOGICAL_OR' => "'or'",
		'T_LOGICAL_XOR' => "'xor'",
		'T_LOGICAL_AND' => "'and'",
		'T_YIELD_FROM' => "'yield from'",
		'T_DOUBLE_ARROW' => "'=>'",
		'T_PLUS_EQUAL' => "'+='",
		'T_MINUS_EQUAL' => "'-='",
		'T_MUL_EQUAL' => "'*='",
		'T_DIV_EQUAL' => "'/='",
		'T_CONCAT_EQUAL' => "'.='",
		'T_MOD_EQUAL' => "'%='",
		'T_AND_EQUAL' => "'&='",
		'T_OR_EQUAL' => "'|='",
		'T_XOR_EQUAL' => "'^='",
		'T_SL_EQUAL' => "'<<='",
		'T_SR_EQUAL' => "'>>='",
		'T_POW_EQUAL' => "'**='",
		'T_COALESCE_EQUAL' => "'??='",
		'T_COALESCE' => "'??'",
		'T_BOOLEAN_OR' => "'||'",
		'T_BOOLEAN_AND' => "'&&'",
		'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG' => "'&'",
		'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG' => "'&'",
		'T_IS_EQUAL' => "'=='",
		'T_IS_NOT_EQUAL' => "'!='",
		'T_IS_IDENTICAL' => "'==='",
		'T_IS_NOT_IDENTICAL' => "'!=='",
		'T_SPACESHIP' => "'<=>'",
		'T_IS_SMALLER_OR_EQUAL' => "'<='",
		'T_IS_GREATER_OR_EQUAL' => "'>='",
		'T_PIPE' => "'|>'",
		'T_SL' => "'<<'",
		'T_SR' => "'>>'",
		'T_INC' => "'++'",
		'T_DEC' => "'--'",
		'T_POW' => "'**'",
		'T_HALT_COMPILER' => "'__halt_compiler'",
		'T_PUBLIC_SET' => "'public(set)'",
		'T_PROTECTED_SET' => "'protected(set)'",
		'T_PRIVATE_SET' => "'private(set)'",
		'T_OBJECT_OPERATOR' => "'->'",
		'T_NULLSAFE_OBJECT_OPERATOR' => "'?->'",
		'T_CLASS_C' => "'__CLASS__'",
		'T_TRAIT_C' => "'__TRAIT__'",
		'T_METHOD_C' => "'__METHOD__'",
		'T_FUNC_C' => "'__FUNCTION__'",
		'T_PROPERTY_C' => "'__PROPERTY__'",
		'T_LINE' => "'__LINE__'",
		'T_FILE' => "'__FILE__'",
		'T_NS_C' => "'__NAMESPACE__'",
		'T_DIR' => "'__DIR__'",
		'T_DOLLAR_OPEN_CURLY_BRACES' => "'\${'",
		'T_CURLY_OPEN' => "'{\$'",
		'T_PAAMAYIM_NEKUDOTAYIM' => "'::'",
		'T_NS_SEPARATOR' => "'\\'",
		'T_ELLIPSIS' => "'...'",
		'T_ATTRIBUTE' => "'#['",
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
				throw new \LogicException('The __halt_compiler() data has no halt statement before it.');
			}

			$halt->data = $data;
		}

		/** @var NodeList<StatementNode> $stmts */
		return new FileNode($this->nestNamespaces($stmts), $eof);
	}


	/**
	 * Moves the statements following `namespace A;` into that namespace, so that every namespace has its
	 * statements as children; only `__halt_compiler()` stays outside.
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
					>= self::NumNonLeafStates: shift and reduce
					> 0: shift
					= 0: accept
					< 0: reduce
					= -self::UnexpectedTokenRule: error
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
	 * The grammar parses `[1,]` and `[]` with an empty item after the last comma; that item is really
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
	 * (`"$a[0]"`); anything else is a string offset, leading zeros and all (`"$a[01]"` reads the key `'01'`).
	 */
	protected function offsetNumber(Token $token): IntegerNode|UnquotedStringNode
	{
		return $token->text === (string) (int) $token->text
			? new IntegerNode($token)
			: new UnquotedStringNode($token);
	}


	/**
	 * The destructuring a target is: a short array written where a place is assigned to means the same as
	 * `list(...)` and becomes one, its own items included, however deep they nest. Anything else stands as it is,
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

				$expected[] = self::TokenNames[$name] ?? (str_starts_with($name, 'T_') ? "'" . strtolower(substr($name, 2)) . "'" : $name);
			}
		}

		return $expected;
	}
}
