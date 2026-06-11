<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Analyses;

use PhpSyntax\{Helpers, NameForm, Node, SymbolKind, Token, UnqualifiedResolution};
use PhpSyntax\Nodes\{ClassLikeNode, ConstItemNode, FileNode, NameNode, PlainNodeList, UseItemNode};
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Scalar\StringNode;
use PhpSyntax\Nodes\Statement\{ClassNode, ConstNode, EnumNode, FunctionNode, InterfaceNode, NamespaceNode, TraitNode, UseNode};
use function count, in_array, is_string, strlen;


/**
 * Resolves names of classes, functions and constants the way PHP does: against the namespace and the imports
 * in effect at the node. An unqualified function or constant the namespace is not known to declare is taken
 * as global (the runtime fallback).
 */
final class NameResolver
{
	private const
		Classes = 'classes',
		Functions = 'functions',
		Constants = 'constants';

	private const SpecialClasses = ['self' => true, 'static' => true, 'parent' => true];

	/** @var array<int, string>  namespace name by the id of the namespace node, 0 for the file */
	private array $namespaces = [];

	/** @var array<int, array<string, array<string, string>>>  scope => table => key of the alias => fully qualified name */
	private array $imports = [];

	/** @var array<int, array<string, array<string, string>>>  scope => table => key of the alias => the alias as it is written */
	private array $aliases = [];

	/** @var array<string, array<string, array<string, (ClassLikeNode&Node)|FunctionNode|ConstItemNode>>>|null  lowercased namespace => table => key of the name => its first declaration in the file */
	private ?array $declared = null;

	/** @var array<string, array<string, array<string, true>>>  the names of $declared declared directly in the namespace, not under a condition or in a function */
	private array $unconditional = [];

	/** the revision of the tree what is read above stands for */
	private int $revision;


	public function __construct(
		private readonly FileNode $file,
		/** what the namespaces declare outside the file */
		private readonly NamespacedSymbols $symbols = new NamespacedSymbols,
	) {
		$this->revision = $file->revision;
	}


	/** Namespace at the node without a leading backslash, `''` in the global namespace. */
	public function getNamespace(Node|Token $at): string
	{
		return $this->namespaces[$this->getScope($at)];
	}


	/**
	 * The imports of the kind in effect at the node.
	 * @return array<string, string>  alias => fully qualified name; the alias lowercased but for a constant
	 */
	public function getImports(SymbolKind $kind, Node|Token $at): array
	{
		return $this->imports[$this->getScope($at)][self::toTable($kind)];
	}


	/**
	 * Fully qualified name of the symbol the name stands for where it stands, its `$symbolKind` choosing among
	 * `resolveClass()`, `resolveFunction()` and `resolveConstant()`; a detached name is read as a class.
	 * @throws \InvalidArgumentException
	 */
	public function resolve(NameNode $name, Node|Token|null $at = null): string
	{
		return match ($name->symbolKind) {
			SymbolKind::ClassLike => $this->resolveClass($name, $at),
			SymbolKind::Function => $this->resolveFunction($name, $at),
			SymbolKind::Constant => $this->resolveConstant($name, $at),
		};
	}


	/**
	 * Fully qualified class name without a leading backslash; `self`, `static` and `parent` stay as they are.
	 * The name may stand outside the file, a clone of a part of it for instance, and `$at` then says where
	 * in the file it is to be read. A name that refers to no symbol (`NameNode::isReference()`) is refused,
	 * as `resolveFunction()` and `resolveConstant()` refuse it, instead of being read as a class of the namespace.
	 * @throws \InvalidArgumentException
	 */
	public function resolveClass(NameNode $name, Node|Token|null $at = null): string
	{
		$parts = $name->parts;
		if (count($parts) === 1 && isset(self::SpecialClasses[strtolower($parts[0])])) {
			return $parts[0];
		}

		return $this->resolveIn($name, self::Classes, $at);
	}


	/**
	 * Fully qualified function name without a leading backslash, the runtime fallback of an unqualified one
	 * to the global function included; a name that refers to no symbol (`NameNode::isReference()`) is refused.
	 * @throws \InvalidArgumentException
	 */
	public function resolveFunction(NameNode $name, Node|Token|null $at = null): string
	{
		return $this->resolveIn($name, self::Functions, $at);
	}


	/**
	 * Fully qualified constant name without a leading backslash, the runtime fallback of an unqualified one
	 * to the global constant included; a name that refers to no symbol (`NameNode::isReference()`) is refused.
	 * @throws \InvalidArgumentException
	 */
	public function resolveConstant(NameNode $name, Node|Token|null $at = null): string
	{
		return $this->resolveIn($name, self::Constants, $at);
	}


	/**
	 * Whether the node is a call of a global function, optionally of the given one. An unqualified name counts
	 * as global unless an import brings in a function of a namespace or the file declares one in the namespace, under
	 * a condition too, so a call the namespace may take over from outside the file counts as well; whoever rewrites
	 * the call asks `getUnqualifiedResolution()` whether it is certain.
	 */
	public function isGlobalFunctionCall(Node $node, ?string $name = null): bool
	{
		$resolved = $node instanceof FunctionCallNode ? $this->resolveGlobalFunction($node) : null;
		return $resolved !== null && ($name === null || strcasecmp($resolved, $name) === 0);
	}


	/**
	 * Which of the global functions the call calls, as it is given among the names, the letter case aside; null where
	 * it calls none of them. The call counts as global the way `isGlobalFunctionCall()` counts it. An item under a string
	 * key is named by the key, so a map of the functions to what a tool does with them goes as it is
	 * (`['count' => ..., 'strlen' => ...]`), and one under an integer key by its value.
	 * @param  iterable<int, string>|iterable<string, mixed>  $names
	 */
	public function findGlobalFunction(FunctionCallNode $call, iterable $names): ?string
	{
		$resolved = $this->resolveGlobalFunction($call);
		if ($resolved !== null) {
			foreach ($names as $key => $value) {
				$name = is_string($key) ? $key : $value;
				if (strcasecmp($resolved, $name) === 0) {
					return $name;
				}
			}
		}

		return null;
	}


	/** The global function the call calls; null for a function of a namespace and a call not made by a name. */
	private function resolveGlobalFunction(FunctionCallNode $call): ?string
	{
		if (!$call->name instanceof NameNode || $call->name->isKeyword()) {
			return null;
		}

		$resolved = $this->resolveFunction($call->name);
		return str_contains($resolved, '\\') ? null : $resolved;
	}


	/**
	 * What the unqualified name reaches at the node. An import decides first, and it may bring in a symbol of another
	 * name, so `Global` does not say the name reaches the global symbol it spells. Otherwise a class is the one of the
	 * namespace, and a function or a constant the one the namespace declares, in the file or among the namespaced
	 * symbols, else the global one: for certain in the global namespace, where the namespaced symbols are complete,
	 * and for `true`, `false` and `null`, and uncertain elsewhere and where the file declares it only in code that
	 * may not run, under a condition or in a function. Only an unqualified name falls back, and `self`, `static` and
	 * `parent` name no symbol, so anything else is refused. A `NameNode` brings its kind and its place, `$symbolKind` and
	 * the node itself, a string needs both given.
	 * @throws \InvalidArgumentException
	 */
	public function getUnqualifiedResolution(
		string|NameNode $name,
		?SymbolKind $kind = null,
		Node|Token|null $at = null,
	): UnqualifiedResolution
	{
		if ($name instanceof NameNode) {
			if ($kind !== null || $at !== null) {
				throw new \InvalidArgumentException('A name given as a node brings its kind and its place, so `$kind` and `$at` stay null.');
			}

			$at = $name;
			$kind = $name->symbolKind;
			$name = $name->text;
		} elseif ($kind === null || $at === null) {
			throw new \InvalidArgumentException('A name given as a string needs `$kind` and `$at`.');
		}

		if (!preg_match('~^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$~D', $name)) {
			throw new \InvalidArgumentException(Helpers::formatCode($name) . ' is not an unqualified name.');
		} elseif ($kind === SymbolKind::ClassLike && isset(self::SpecialClasses[strtolower($name)])) {
			throw new \InvalidArgumentException(Helpers::formatCode($name) . ' names no class of its own; it stands for one only where it is written.');
		}

		$scope = $this->getScope($at);
		$table = self::toTable($kind);
		$key = self::toKey($table, $name);
		$import = $this->imports[$scope][$table][$key] ?? null;
		return match (true) {
			$import !== null => str_contains($import, '\\') ? UnqualifiedResolution::Namespaced : UnqualifiedResolution::Global,
			$this->namespaces[$scope] === '',
			$table === self::Constants && in_array(strtolower($name), ['true', 'false', 'null'], true) => UnqualifiedResolution::Global,
			$table === self::Classes, $this->isDeclared($scope, $table, $key) => UnqualifiedResolution::Namespaced,
			$this->isDeclared($scope, $table, $key, anywhere: true) => UnqualifiedResolution::Uncertain,
			$this->symbols->complete => UnqualifiedResolution::Global,
			default => UnqualifiedResolution::Uncertain,
		};
	}


	/**
	 * The shortest way to write the fully qualified name at the node: the alias of an import, the alias of an import
	 * of its prefix followed by the rest, the name relative to the namespace where no import takes its first part, else
	 * the fully qualified name. A function and a constant are written bare only where the bare name reaches them for
	 * certain; a qualified name of either reads its first part through the imports of classes, as a class does.
	 */
	public function shortenName(string $fullName, SymbolKind $kind, Node|Token $at): string
	{
		$fullName = ltrim($fullName, '\\');
		$scope = $this->getScope($at);
		$table = self::toTable($kind);
		$caseSensitive = $table === self::Constants;
		$shortest = null;
		foreach ($this->imports[$scope][$table] as $key => $imported) {
			$alias = $this->aliases[$scope][$table][$key];
			if (self::sameName($imported, $fullName, $caseSensitive) && ($shortest === null || strlen($alias) < strlen($shortest))) {
				$shortest = $alias;
			}
		}

		// an import of a prefix shortens the rest of the name too, a qualified name of any kind reading its first part
		// through the imports of classes and namespaces
		foreach ($this->imports[$scope][self::Classes] as $key => $imported) {
			$alias = $this->aliases[$scope][self::Classes][$key];
			if (
				str_starts_with(strtolower($fullName), strtolower($imported) . '\\')
				&& ($shortest === null || strlen($alias) + strlen($fullName) - strlen($imported) < strlen($shortest))
			) {
				$shortest = $alias . substr($fullName, strlen($imported));
			}
		}

		$namespace = $this->namespaces[$scope];
		$relative = match (true) {
			$namespace === '' => $fullName, // in the global namespace a name stands for itself
			str_starts_with(strtolower($fullName), strtolower($namespace) . '\\') => substr($fullName, strlen($namespace) + 1),
			default => null,
		};
		if (
			$relative !== null
			&& ($table === self::Classes || str_contains($relative, '\\')) // a bare function or constant may fall back, below
			&& !isset($this->imports[$scope][self::Classes][strtolower(explode('\\', $relative)[0])])
			&& ($shortest === null || strlen($relative) <= strlen($shortest))
		) {
			$shortest = $relative;
		}

		if ($table === self::Classes) {
			return $shortest ?? '\\' . $fullName;
		}

		$pos = strrpos($fullName, '\\');
		$short = $pos === false ? $fullName : substr($fullName, $pos + 1);
		$key = self::toKey($table, $short);
		$bare = $pos === false
			// a global one falls back to itself where nothing of that name is anywhere in the namespace, and for certain
			? $this->isAliasFree($short, $kind, $at) && $this->getUnqualifiedResolution($short, $kind, $at) === UnqualifiedResolution::Global
			// one of the namespace itself is what the bare name reaches first, where it is known to exist
			: strcasecmp(substr($fullName, 0, $pos), $namespace) === 0
				&& !isset($this->imports[$scope][$table][$key])
				&& $this->isDeclared($scope, $table, $key);
		return $bare && ($shortest === null || strlen($short) <= strlen($shortest))
			? $short
			: $shortest ?? '\\' . $fullName;
	}


	/**
	 * Whether the alias is free at the node: no import of the kind takes it, and nothing declared
	 * in the namespace does either.
	 */
	public function isAliasFree(string $alias, SymbolKind $kind, Node|Token $at): bool
	{
		$scope = $this->getScope($at);
		$table = self::toTable($kind);
		$key = self::toKey($table, $alias);
		return !isset($this->imports[$scope][$table][$key])
			&& !$this->isDeclared($scope, $table, $key, anywhere: true);
	}


	/**
	 * The fully qualified name a class-like, a function or a constant of a `const` statement introduces into
	 * its namespace, without a leading backslash; null for anything else, an anonymous class and a class
	 * constant among them.
	 */
	public function getDeclaredName(Node $node): ?string
	{
		$name = match (true) {
			$node instanceof ClassLikeNode, $node instanceof FunctionNode => $node->name,
			$node instanceof ConstItemNode && $node->parent?->parent instanceof ConstNode => $node->name,
			default => null,
		};
		return $name === null ? null : self::prefix($this->getNamespace($node), $name->text);
	}


	/**
	 * The first declaration of the fully qualified name in the file among the symbols of the kind, PHP keeping
	 * a class, a function and a constant of one name apart; null when the file declares it nowhere.
	 * @return ($kind is SymbolKind::ClassLike ? (ClassLikeNode&Node)|null : ($kind is SymbolKind::Function ? FunctionNode|null : ConstItemNode|null))
	 */
	public function findDeclaration(
		string $fullName,
		SymbolKind $kind,
	): (ClassLikeNode&Node)|FunctionNode|ConstItemNode|null
	{
		$fullName = ltrim($fullName, '\\');
		$pos = strrpos($fullName, '\\');
		$namespace = $pos === false ? '' : substr($fullName, 0, $pos);
		$name = $pos === false ? $fullName : substr($fullName, $pos + 1);
		$table = self::toTable($kind);
		return $this->getDeclarations()[strtolower($namespace)][$table][self::toKey($table, $name)] ?? null;
	}


	/**
	 * The functions and constants the node declares into a namespace, with a declaration inside a condition or a
	 * function body and a constant `define()` names with a string, in the order they are written. What is declared into
	 * the global namespace is not among them.
	 * @return list<Declaration>
	 */
	public function findNamespacedDeclarations(Node $node): array
	{
		$declarations = [];
		$candidates = $node->find(Node::class, fn(Node $inner) => $inner instanceof FunctionNode
			|| $inner instanceof ConstNode
			|| $inner instanceof FunctionCallNode);
		foreach ($candidates as $inner) {
			if ($inner instanceof FunctionNode) {
				$declarations[] = new Declaration(SymbolKind::Function, (string) $this->getDeclaredName($inner), $inner->name);
			} elseif ($inner instanceof ConstNode) {
				foreach ($inner->items->getItems() as $item) {
					$declarations[] = new Declaration(SymbolKind::Constant, (string) $this->getDeclaredName($item), $item);
				}
			} elseif ($inner instanceof FunctionCallNode && $this->isGlobalFunctionCall($inner, 'define')) {
				// a name with a leading backslash declares a constant no name reaches, not the one it spells
				$argument = $inner->arguments->findArgument('constant_name', 0)?->value;
				if ($argument instanceof StringNode && !str_starts_with($argument->value, '\\')) {
					$declarations[] = new Declaration(SymbolKind::Constant, $argument->value, $argument);
				}
			}
		}

		return array_values(array_filter($declarations, fn(Declaration $declaration) => str_contains($declaration->name, '\\')));
	}


	/**
	 * Whether two fully qualified names are one symbol: a namespace is read in any letter case, and so is
	 * the name itself unless it is case-sensitive, as the name of a constant is.
	 */
	private static function sameName(string $a, string $b, bool $caseSensitive): bool
	{
		return strcasecmp($a, $b) === 0
			&& (!$caseSensitive || substr($a, (int) strrpos('\\' . $a, '\\')) === substr($b, (int) strrpos('\\' . $b, '\\')));
	}


	private function resolveIn(NameNode $name, string $table, Node|Token|null $at): string
	{
		if (!$name->isReference()) {
			throw new \InvalidArgumentException(Helpers::formatCode($name->text) . ' refers to no symbol: it declares one, names a builtin type, or is `self`, `static` or `parent`.');
		}

		$scope = $this->getScope($at ?? $name);
		$namespace = $this->namespaces[$scope];
		$parts = $name->parts;
		switch ($name->form) {
			case NameForm::FullyQualified:
				return implode('\\', $parts);

			case NameForm::Relative:
				return self::prefix($namespace, implode('\\', $parts));

			case NameForm::Qualified:
				$alias = $this->imports[$scope][self::Classes][strtolower($parts[0])] ?? null;
				return $alias === null
					? self::prefix($namespace, implode('\\', $parts))
					: self::prefix($alias, implode('\\', array_slice($parts, 1)));

			default:
				$key = self::toKey($table, $parts[0]);
				$import = $this->imports[$scope][$table][$key] ?? null;
				if ($import !== null) {
					return $import;
				} elseif ($namespace === '' || ($table !== self::Classes && !$this->isDeclared($scope, $table, $key, anywhere: true))) {
					return $parts[0];
				}

				return self::prefix($namespace, $parts[0]);
		}
	}


	private static function prefix(string $namespace, string $name): string
	{
		return $namespace === '' ? $name : "$namespace\\$name";
	}


	/** Returns the id of the scope of the node, building it on first use. */
	private function getScope(Node|Token $node): int
	{
		if (($node instanceof Node ? $node : $node->parent)?->getFile() !== $this->file) {
			throw new \LogicException('The node does not belong to the file of the analysis.');
		}

		$this->refresh();
		$namespace = $node instanceof NamespaceNode ? $node : $node->findAncestor(NamespaceNode::class);
		$id = $namespace ? spl_object_id($namespace) : 0;
		if (!isset($this->namespaces[$id])) {
			$this->buildScope($id, $namespace);
		}

		return $id;
	}


	private function buildScope(int $id, ?NamespaceNode $namespace): void
	{
		$this->namespaces[$id] = $namespace?->name ? implode('\\', $namespace->name->parts) : '';
		$this->imports[$id] = $this->aliases[$id] = [self::Classes => [], self::Functions => [], self::Constants => []];

		foreach (($namespace->statements ?? $this->file->statements)->getItems() as $stmt) {
			if ($stmt instanceof UseNode) {
				foreach ($stmt->items->getItems() as $item) {
					$this->addImport($id, $item);
				}
			}
		}
	}


	/**
	 * Whether the namespace of the scope declares the symbol, in the file or among the namespaced symbols;
	 * a declaration belongs to the namespace, so every block of it counts. One under a condition or in a function
	 * declares it only once that code runs, so it counts only where `$anywhere` asks for it.
	 */
	private function isDeclared(int $scope, string $table, string $key, bool $anywhere = false): bool
	{
		$namespace = $this->namespaces[$scope];
		$all = $this->getDeclarations();
		$declared = $anywhere ? $all : $this->unconditional;
		return isset($declared[strtolower($namespace)][$table][$key])
			|| ($namespace !== '' && match ($table) {
				self::Functions => $this->symbols->hasFunction("$namespace\\$key"),
				self::Constants => $this->symbols->hasConstant("$namespace\\$key"),
				default => false,
			});
	}


	/**
	 * What each namespace of the file declares, nested declarations too; of several declarations of one name the first.
	 * @return array<string, array<string, array<string, (ClassLikeNode&Node)|FunctionNode|ConstItemNode>>>
	 */
	private function collectDeclarations(): array
	{
		$declared = [];
		$isDeclaration = fn(Node $node): bool => $node instanceof FunctionNode
			|| $node instanceof ClassNode
			|| $node instanceof InterfaceNode
			|| $node instanceof TraitNode
			|| $node instanceof EnumNode
			|| $node instanceof ConstNode;
		foreach ($this->file->statements->getItems() as $stmt) {
			$namespace = $stmt instanceof NamespaceNode && $stmt->name
				? strtolower(implode('\\', $stmt->name->parts))
				: '';
			foreach ([$stmt, ...$stmt->find(Node::class, $isDeclaration)] as $declaration) {
				[$table, $named] = match (true) {
					$declaration instanceof FunctionNode => [self::Functions, [$declaration]],
					$declaration instanceof ConstNode => [self::Constants, $declaration->items->getItems()],
					$declaration instanceof ClassLikeNode && $declaration->name !== null => [self::Classes, [$declaration]],
					default => [null, []],
				};
				if ($table === null) {
					continue;
				}

				// what stands in the statements of the file or of a namespace is declared as the file loads
				$unconditional = ($list = $declaration->parent) instanceof PlainNodeList
					&& ($list->parent instanceof FileNode || $list->parent instanceof NamespaceNode);
				foreach ($named as $item) {
					$key = self::toKey($table, $item->name->text);
					$declared[$namespace][$table][$key] ??= $item;
					if ($unconditional) {
						$this->unconditional[$namespace][$table][$key] = true;
					}
				}
			}
		}

		return $declared;
	}


	/**
	 * What each namespace of the file declares, read once for the revision of the tree.
	 * @return array<string, array<string, array<string, (ClassLikeNode&Node)|FunctionNode|ConstItemNode>>>
	 */
	private function getDeclarations(): array
	{
		$this->refresh();
		return $this->declared ??= $this->collectDeclarations();
	}


	/** Forgets everything read from the tree once it has changed since. */
	private function refresh(): void
	{
		if ($this->revision !== $this->file->revision) {
			$this->revision = $this->file->revision;
			$this->namespaces = $this->imports = $this->aliases = $this->unconditional = [];
			$this->declared = null;
		}
	}


	private function addImport(int $id, UseItemNode $item): void
	{
		$table = self::toTable($item->symbolKind);
		$target = $item->fullName;
		$alias = $item->alias === null ? $item->name->shortName : $item->alias->text;
		$key = self::toKey($table, $alias);
		$this->imports[$id][$table][$key] = $target;
		$this->aliases[$id][$table][$key] = $alias;
	}


	private static function toTable(SymbolKind $kind): string
	{
		return match ($kind) {
			SymbolKind::Function => self::Functions,
			SymbolKind::Constant => self::Constants,
			SymbolKind::ClassLike => self::Classes,
		};
	}


	/** The key a name is kept under in a table: as written for a constant, in lower case for the rest, which PHP reads in any. */
	private static function toKey(string $table, string $name): string
	{
		return $table === self::Constants ? $name : strtolower($name);
	}
}
