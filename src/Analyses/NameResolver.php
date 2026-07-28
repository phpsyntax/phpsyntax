<?php declare(strict_types=1);

namespace PhpSyntax\Analyses;

use PhpSyntax\NameKind;
use PhpSyntax\Node;
use PhpSyntax\Nodes\ClassLikeNode;
use PhpSyntax\Nodes\ConstItemNode;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\Statement\ConstNode;
use PhpSyntax\Nodes\Statement\EnumNode;
use PhpSyntax\Nodes\Statement\FunctionNode;
use PhpSyntax\Nodes\Statement\InterfaceNode;
use PhpSyntax\Nodes\Statement\NamespaceNode;
use PhpSyntax\Nodes\Statement\TraitNode;
use PhpSyntax\Nodes\Statement\UseNode;
use PhpSyntax\Nodes\UseItemNode;
use PhpSyntax\SymbolKind;
use PhpSyntax\Token;
use PhpSyntax\UnqualifiedResolution;
use function count, in_array, strlen;


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

	/** @var array<int, array<string, array<string, string>>>  table → alias → fully qualified name */
	private array $imports = [];

	/** @var array<int, array<string, array<string, string>>>  table → key of the import → the alias as it is written */
	private array $aliases = [];

	/** @var array<string, array<string, true>>|null  lowercased namespace → table:name it declares in the file */
	private ?array $declared = null;


	public function __construct(
		private readonly FileNode $file,
		/** what the namespaces declare outside the file */
		private readonly NamespacedSymbols $symbols = new NamespacedSymbols,
	) {
	}


	/** Namespace of the node without a leading backslash, '' in the global namespace. */
	public function getNamespace(Node|Token $node): string
	{
		return $this->namespaces[$this->getScope($node)];
	}


	/** @return array<string, string>  lowercased alias → fully qualified name of the class imports in effect at the node */
	public function getClassImports(Node|Token $node): array
	{
		return $this->imports[$this->getScope($node)][self::Classes];
	}


	/** @return array<string, string>  lowercased alias → fully qualified name */
	public function getFunctionImports(Node|Token $node): array
	{
		return $this->imports[$this->getScope($node)][self::Functions];
	}


	/** @return array<string, string>  alias → fully qualified name, case-sensitive */
	public function getConstantImports(Node|Token $node): array
	{
		return $this->imports[$this->getScope($node)][self::Constants];
	}


	/**
	 * Fully qualified class name without a leading backslash; self, static and parent stay as they are.
	 * The name may stand outside the file, a clone of a part of it for instance, and $at then says where
	 * in the file it is to be read.
	 */
	public function resolveClass(NameNode $name, Node|Token|null $at = null): string
	{
		$parts = $name->parts;
		if (count($parts) === 1 && isset(self::SpecialClasses[strtolower($parts[0])])) {
			return $parts[0];
		}

		return $this->resolve($name, self::Classes, $at);
	}


	public function resolveFunction(NameNode $name, Node|Token|null $at = null): string
	{
		return $this->resolve($name, self::Functions, $at);
	}


	public function resolveConstant(NameNode $name, Node|Token|null $at = null): string
	{
		return $this->resolve($name, self::Constants, $at);
	}


	/**
	 * Whether the node is a call of a global function, optionally of the given one. An unqualified name counts
	 * as global unless an import brings in a function of a namespace or the namespace is known to declare it,
	 * for certain or not, which getUnqualifiedResolution() tells apart.
	 */
	public function isGlobalFunctionCall(Node $node, ?string $name = null): bool
	{
		if (!$node instanceof FunctionCallNode || !$node->name instanceof NameNode || $node->name->isKeyword()) {
			return false;
		}

		$resolved = $this->resolveFunction($node->name);
		return !str_contains($resolved, '\\')
			&& ($name === null || strcasecmp($resolved, $name) === 0);
	}


	/**
	 * What the unqualified name reaches at the node. An import decides first, and it may bring in a symbol of another
	 * name, so Global does not say the name reaches the global symbol it spells. Otherwise a class is the one of the
	 * namespace, and a function or a constant the one the namespace declares, in the file or among the namespaced
	 * symbols, else the global one: for certain in the global namespace, where the namespaced symbols are complete,
	 * and for true, false and null, and uncertain elsewhere. Only an unqualified name falls back, and self, static and
	 * parent name no symbol, so anything else is refused.
	 * @throws \InvalidArgumentException
	 */
	public function getUnqualifiedResolution(string $name, SymbolKind $kind, Node|Token $at): UnqualifiedResolution
	{
		$node = NameNode::fromText($name);
		if (!$node->isUnqualified()) {
			throw new \InvalidArgumentException("'$name' is not an unqualified name.");
		} elseif ($kind === SymbolKind::ClassLike && $node->isSpecialClass()) {
			throw new \InvalidArgumentException("'$name' names no class of its own, it stands for one only where it is written.");
		}

		$scope = $this->getScope($at);
		$table = self::toTable($kind);
		$key = $table === self::Constants ? $name : strtolower($name);
		$import = $this->imports[$scope][$table][$key] ?? null;
		return match (true) {
			$import !== null => str_contains($import, '\\') ? UnqualifiedResolution::Namespaced : UnqualifiedResolution::Global,
			$this->namespaces[$scope] === '',
			$table === self::Constants && in_array(strtolower($name), ['true', 'false', 'null'], true) => UnqualifiedResolution::Global,
			$table === self::Classes, $this->isDeclared($scope, $table, $key) => UnqualifiedResolution::Namespaced,
			$this->symbols->complete => UnqualifiedResolution::Global,
			default => UnqualifiedResolution::Uncertain,
		};
	}


	/**
	 * The shortest way to write the fully qualified name at the node: the alias of an import, else the name
	 * relative to the namespace where nothing shadows it, else the fully qualified name. A function and a
	 * constant are written bare only where the bare name reaches them for certain.
	 */
	public function getShortName(string $fullName, SymbolKind $kind, Node|Token $at): string
	{
		$fullName = ltrim($fullName, '\\');
		$scope = $this->getScope($at);
		$table = self::toTable($kind);
		$caseSensitive = $table === self::Constants;
		$shortest = null;
		foreach ($this->imports[$scope][$table] as $key => $imported) {
			if (self::sameName($imported, $fullName, $caseSensitive)) {
				$shortest = $this->aliases[$scope][$table][$key];
			}
		}

		$namespace = $this->namespaces[$scope];
		if ($table === self::Classes) {
			// an import of a prefix shortens the rest of the name too
			foreach ($this->imports[$scope][self::Classes] as $key => $imported) {
				$alias = $this->aliases[$scope][self::Classes][$key];
				if (
					str_starts_with(strtolower($fullName), strtolower($imported) . '\\')
					&& ($shortest === null || strlen($alias) + strlen($fullName) - strlen($imported) < strlen($shortest))
				) {
					$shortest = $alias . substr($fullName, strlen($imported));
				}
			}

			$relative = match (true) {
				$namespace === '' => $fullName, // in the global namespace a name stands for itself
				str_starts_with(strtolower($fullName), strtolower($namespace) . '\\') => substr($fullName, strlen($namespace) + 1),
				default => null,
			};
			if ($relative !== null && $this->isAliasFree(explode('\\', $relative)[0], $kind, $at)) {
				return $shortest === null || strlen($relative) <= strlen($shortest) ? $relative : $shortest;
			}

			return $shortest ?? '\\' . $fullName;
		}

		$pos = strrpos($fullName, '\\');
		$short = $pos === false ? $fullName : substr($fullName, $pos + 1);
		$key = $caseSensitive ? $short : strtolower($short);
		$bare = $pos === false
			// a global one falls back to itself where nothing of that name is anywhere in the namespace, and for certain
			? $this->isAliasFree($short, $kind, $at) && $this->getUnqualifiedResolution($short, $kind, $at) === UnqualifiedResolution::Global
			// one of the namespace itself is what the bare name reaches first, where it is known to exist
			: strcasecmp(substr($fullName, 0, $pos), $namespace) === 0
				&& !isset($this->imports[$scope][$table][$key])
				&& $this->isDeclared($scope, $table, $key);
		return $shortest ?? ($bare ? $short : '\\' . $fullName);
	}


	/**
	 * Whether the alias is free at the node: no import of the kind takes it, and nothing declared
	 * in the namespace does either.
	 */
	public function isAliasFree(string $alias, SymbolKind $kind, Node|Token $at): bool
	{
		$scope = $this->getScope($at);
		$table = self::toTable($kind);
		$key = $table === self::Constants ? $alias : strtolower($alias);
		return !isset($this->imports[$scope][$table][$key])
			&& !$this->isDeclared($scope, $table, $key);
	}


	/**
	 * The fully qualified name a class-like, a function or a constant of a const statement introduces into
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
	 * The declaration of the fully qualified name in the file among the symbols of the kind, PHP keeping
	 * a class, a function and a constant of one name apart; null when the file declares it nowhere.
	 * @return ($kind is SymbolKind::ClassLike ? (ClassLikeNode&Node)|null : ($kind is SymbolKind::Function ? FunctionNode|null : ConstItemNode|null))
	 */
	public function findDeclaration(
		string $fullName,
		SymbolKind $kind,
	): (ClassLikeNode&Node)|FunctionNode|ConstItemNode|null
	{
		$fullName = ltrim($fullName, '\\');
		$declares = fn(Node $node): bool => self::sameName((string) $this->getDeclaredName($node), $fullName, $kind === SymbolKind::Constant);
		return match ($kind) {
			SymbolKind::ClassLike => $this->file->findFirst(ClassLikeNode::class, $declares),
			SymbolKind::Function => $this->file->findFirst(FunctionNode::class, $declares),
			SymbolKind::Constant => $this->file->findFirst(ConstItemNode::class, $declares),
		};
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


	private function resolve(NameNode $name, string $table, Node|Token|null $at): string
	{
		$scope = $this->getScope($at ?? $name);
		$namespace = $this->namespaces[$scope];
		$parts = $name->parts;
		switch ($name->kind) {
			case NameKind::FullyQualified:
				return implode('\\', $parts);

			case NameKind::Relative:
				return self::prefix($namespace, implode('\\', $parts));

			case NameKind::Qualified:
				$alias = $this->imports[$scope][self::Classes][strtolower($parts[0])] ?? null;
				return $alias === null
					? self::prefix($namespace, implode('\\', $parts))
					: self::prefix($alias, implode('\\', array_slice($parts, 1)));

			default:
				$key = $table === self::Constants ? $parts[0] : strtolower($parts[0]);
				$import = $this->imports[$scope][$table][$key] ?? null;
				if ($import !== null) {
					return $import;
				} elseif ($namespace === '' || ($table !== self::Classes && !$this->isDeclared($scope, $table, $key))) {
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

		$namespace = $node instanceof NamespaceNode ? $node : $node->parent?->findAncestor(NamespaceNode::class);
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
	 * a declaration belongs to the namespace, so every block of it counts.
	 */
	private function isDeclared(int $scope, string $table, string $key): bool
	{
		$this->declared ??= $this->collectDeclarations();
		$namespace = $this->namespaces[$scope];
		return isset($this->declared[strtolower($namespace)]["$table:$key"])
			|| ($namespace !== '' && match ($table) {
				self::Functions => $this->symbols->hasFunction("$namespace\\$key"),
				self::Constants => $this->symbols->hasConstant("$namespace\\$key"),
				default => false,
			});
	}


	/** @return array<string, array<string, true>>  what each namespace of the file declares, nested declarations too */
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
				if ($declaration instanceof FunctionNode) {
					$declared[$namespace][self::Functions . ':' . strtolower($declaration->name->token->text)] = true;
				} elseif (
					$declaration instanceof ClassNode
					|| $declaration instanceof InterfaceNode
					|| $declaration instanceof TraitNode
					|| $declaration instanceof EnumNode
				) {
					$declared[$namespace][self::Classes . ':' . strtolower($declaration->name->token->text)] = true;
				} elseif ($declaration instanceof ConstNode) {
					foreach ($declaration->items->getItems() as $item) {
						$declared[$namespace][self::Constants . ':' . $item->name->token->text] = true;
					}
				}
			}
		}

		return $declared;
	}


	private function addImport(int $id, UseItemNode $item): void
	{
		$table = self::toTable($item->kind);
		$target = $item->fullName;
		$alias = $item->alias === null ? $item->name->shortName : $item->alias->text;
		$key = $table === self::Constants ? $alias : strtolower($alias);
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
}
