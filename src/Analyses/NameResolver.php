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
use function array_slice, count, strlen;


/**
 * Resolves names of classes, functions and constants the way PHP does: against the namespace and the imports
 * in effect at the node. An unqualified function or constant that is neither imported nor declared in the
 * namespace within the file is taken as global (the runtime fallback).
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

		return $this->resolve($name, self::Classes, caseSensitive: false, fallbackToGlobal: false, at: $at);
	}


	public function resolveFunction(NameNode $name, Node|Token|null $at = null): string
	{
		return $this->resolve($name, self::Functions, caseSensitive: false, fallbackToGlobal: true, at: $at);
	}


	public function resolveConstant(NameNode $name, Node|Token|null $at = null): string
	{
		return $this->resolve($name, self::Constants, caseSensitive: true, fallbackToGlobal: true, at: $at);
	}


	/**
	 * Whether the node is a call of a global function, optionally of the given one. The answer is what the file
	 * shows: an unqualified name neither imported nor declared in its namespace within the file counts as global,
	 * the way PHP falls back to it, although another file may declare the function in that namespace.
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
	 * The shortest way to write the fully qualified name at the node: the alias of an import, else the name
	 * relative to the namespace where nothing shadows it, else the fully qualified name. A function and a
	 * constant are shortened only through an import, because what an unqualified one falls back to depends
	 * on what the namespace declares elsewhere.
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

			$namespace = $this->namespaces[$scope];
			$relative = match (true) {
				$namespace === '' => $fullName, // in the global namespace a name stands for itself
				str_starts_with(strtolower($fullName), strtolower($namespace) . '\\') => substr($fullName, strlen($namespace) + 1),
				default => null,
			};
			if ($relative !== null && $this->isAliasFree(explode('\\', $relative)[0], $kind, $at)) {
				return $shortest === null || strlen($relative) <= strlen($shortest) ? $relative : $shortest;
			}
		} elseif (
			$this->namespaces[$scope] === ''
			&& !str_contains($fullName, '\\')
			&& $this->isAliasFree($fullName, $kind, $at) // a name an import has taken over would read as that one
		) {
			// a global function or constant in the global namespace needs no import and no backslash
			return $shortest ?? $fullName;
		}

		return $shortest ?? '\\' . $fullName;
	}


	/**
	 * Whether the alias is free at the node: no import of the kind takes it, and nothing declared
	 * in the namespace within the file does either.
	 */
	public function isAliasFree(string $alias, SymbolKind $kind, Node|Token $at): bool
	{
		$scope = $this->getScope($at);
		$table = self::toTable($kind);
		$key = $table === self::Constants ? $alias : strtolower($alias);
		return !isset($this->imports[$scope][$table][$key])
			&& !$this->isDeclared($scope, "$table:$key");
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


	private function resolve(
		NameNode $name,
		string $table,
		bool $caseSensitive,
		bool $fallbackToGlobal,
		Node|Token|null $at,
	): string
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
				$key = $caseSensitive ? $parts[0] : strtolower($parts[0]);
				$import = $this->imports[$scope][$table][$key] ?? null;
				if ($import !== null) {
					return $import;
				} elseif ($namespace === '' || ($fallbackToGlobal && !$this->isDeclared($scope, "$table:$key"))) {
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
					$this->addImport($id, $item, self::toTable($item->kind));
				}
			}
		}
	}


	/**
	 * Whether the namespace of the scope declares the symbol: imports belong to the block they are written
	 * in, a declaration to the namespace, so every block of it counts.
	 */
	private function isDeclared(int $scope, string $key): bool
	{
		$this->declared ??= $this->collectDeclarations();
		return isset($this->declared[strtolower($this->namespaces[$scope])][$key]);
	}


	/** @return array<string, array<string, true>>  what each namespace of the file declares */
	private function collectDeclarations(): array
	{
		$declared = [];
		foreach ($this->file->statements->getItems() as $stmt) {
			$namespace = $stmt instanceof NamespaceNode && $stmt->name
				? strtolower(implode('\\', $stmt->name->parts))
				: '';
			foreach ($stmt instanceof NamespaceNode ? $stmt->statements->getItems() : [$stmt] as $declaration) {
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


	private function addImport(int $id, UseItemNode $item, string $table): void
	{
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
