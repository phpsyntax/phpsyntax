<?php declare(strict_types=1);

/**
 * Generates the Slots constant and the constructor of every node class from grammar/nodes.php; included by build.php.
 * The rest of a node class is handwritten: in an existing file the two members are replaced in place and nothing
 * else is touched, a missing file is written as a skeleton to complete by hand, a file the schema does not know is
 * reported and left alone.
 */

const NodesNamespace = 'PhpSyntax\Nodes';
const RootClasses = ['Node', 'Token'];
const DefaultParents = ['Expression' => 'ExpressionNode', 'Scalar' => 'ExpressionNode', 'Statement' => 'StatementNode', 'Type' => 'TypeNode', 'Member' => 'MemberNode'];


/**
 * @param array<string, array{slots: array<string, string>, manual?: bool}> $schema
 */
function buildNodes(array $schema, string $dir): void
{
	$known = [];
	foreach ($schema as $class => $definition) {
		$file = $dir . '/' . strtr($class, '\\', '/') . '.php';
		$known[] = normalizePath($file);
		if ($definition['manual'] ?? false) {
			continue;
		}

		[$imports, $slots] = describeSlots($class, $definition['slots']);
		$constant = renderSlotsConstant($slots);
		$constructor = renderConstructor($slots);
		if (is_file($file)) {
			$code = str_replace("\r\n", "\n", (string) file_get_contents($file));
			$code = replaceOnce($code, '~^\tpublic const Slots = \[[^\n]*\];$~m', $constant, "$class: the Slots constant");
			$code = replaceOnce($code, '~(?:^\t/\*\*[^\n]*\*/\n)?^\tpublic function __construct\(.*?\n\t\}~ms', $constructor, "$class: the constructor");
			foreach ($imports as $import) {
				if (!preg_match('~^use ' . preg_quote($import, '~') . ';$~m', $code)) {
					echo "$class: add `use $import;`\n";
				}
			}
		} else {
			$code = renderSkeleton($class, $imports, $constant, $constructor);
			echo "$class: written as a skeleton, complete its docblock and methods\n";
		}

		@mkdir(dirname($file), 0o777, true);
		file_put_contents($file, $code);
	}

	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file) {
		if (
			$file->getExtension() === 'php'
			&& !in_array(normalizePath($file->getPathname()), $known, strict: true)
			&& str_contains((string) file_get_contents($file->getPathname()), "\tpublic const Slots = [")
		) {
			echo "Not in the schema: {$file->getPathname()}\n";
		}
	}
}


function normalizePath(string $path): string
{
	return str_replace('\\', '/', (string) realpath(dirname($path))) . '/' . basename($path);
}


/**
 * Replaces the one occurrence of the pattern; the build fails when the file does not have the shape the generator
 * writes, instead of leaving a class behind the schema.
 */
function replaceOnce(string $code, string $pattern, string $replacement, string $what): string
{
	$count = preg_match_all($pattern, $code);
	if ($count !== 1) {
		throw new Exception("$what was found $count times, expected once; restore the shape the generator writes or delete the file.");
	}

	return (string) preg_replace_callback($pattern, fn() => $replacement, $code, 1);
}


/**
 * @return array{nullable: bool, list: ?string, classes: list<string>}  list = NodeList|SeparatedNodeList, classes = item or slot types
 */
function parseSlotType(string $type): array
{
	$nullable = str_starts_with($type, '?');
	$type = ltrim($type, '?');
	$list = null;
	if (preg_match('~^(NodeList|SeparatedNodeList)<(.+)>$~', $type, $m)) {
		$list = $m[1];
		$type = $m[2];
	}

	return ['nullable' => $nullable, 'list' => $list, 'classes' => explode('|', $type)];
}


function resolveNodeClass(string $class): string
{
	return in_array($class, RootClasses, strict: true)
		? 'PhpSyntax\\' . $class
		: NodesNamespace . '\\' . $class;
}


function namespaceOf(string $class): string
{
	return NodesNamespace . (str_contains($class, '\\') ? '\\' . substr($class, 0, strrpos($class, '\\')) : '');
}


/**
 * The imports the slots need from outside the namespace of the class, and the slots with their native and
 * documented types.
 * @param  array<string, string>  $slotTypes
 * @return array{list<string>, array<string, array{native: string, doc: ?string}>}
 */
function describeSlots(string $class, array $slotTypes): array
{
	$namespace = namespaceOf($class);
	$imports = [];
	$use = function (string $class) use (&$imports, $namespace): string {
		$full = resolveNodeClass($class);
		$short = substr($full, strrpos($full, '\\') + 1);
		if (substr($full, 0, strrpos($full, '\\')) !== $namespace) {
			$imports[$full] = true;
		}

		return $short;
	};

	$slots = [];
	foreach ($slotTypes as $name => $type) {
		$info = parseSlotType($type);
		$shorts = array_map($use, $info['classes']);
		$native = $info['list'] ? $use($info['list']) : implode('|', $shorts);
		$slots[$name] = [
			'native' => ($info['nullable'] ? (count($shorts) > 1 && !$info['list'] ? '' : '?') : '') . $native . ($info['nullable'] && count($shorts) > 1 && !$info['list'] ? '|null' : ''),
			'doc' => $info['list'] ? ($info['nullable'] ? '?' : '') . $native . '<' . implode('|', $shorts) . '>' : null,
		];
	}

	ksort($imports);
	return [array_keys($imports), $slots];
}


/** @param array<string, array{native: string, doc: ?string}> $slots */
function renderSlotsConstant(array $slots): string
{
	return "\tpublic const Slots = [" . implode(', ', array_map(fn(string $name) => "'$name'", array_keys($slots))) . '];';
}


/**
 * The slots as hooked promoted properties in source order, the item type of a list in a @var of its own.
 * The constructor is internal: its parameters follow the grammar, which the next version of PHP may extend.
 * @param array<string, array{native: string, doc: ?string}> $slots
 */
function renderConstructor(array $slots): string
{
	if ($slots === []) {
		return "\t/** @internal */\n\tpublic function __construct()\n\t{\n\t}";
	}

	$lines = ["\t/** @internal */", "\tpublic function __construct("];
	foreach ($slots as $name => $slot) {
		if ($slot['doc']) {
			$lines[] = "\t\t/** @var {$slot['doc']} */";
		}

		$lines[] = "\t\tpublic {$slot['native']} \$$name { set => \$this->prepareSlot(__PROPERTY__, \$value); },";
	}

	$lines[] = "\t) {";
	$lines[] = "\t}";
	return implode("\n", $lines);
}


/**
 * A new class: the parent by the directory convention, the two generated members, and a docblock to write.
 * @param list<string> $imports
 */
function renderSkeleton(string $class, array $imports, string $constant, string $constructor): string
{
	$namespace = namespaceOf($class);
	$shortName = substr($class, strrpos($class, '\\') !== false ? strrpos($class, '\\') + 1 : 0);
	$parent = resolveNodeClass(DefaultParents[explode('\\', $class)[0]] ?? 'Node');
	$parentShort = substr($parent, strrpos($parent, '\\') + 1);
	if (substr($parent, 0, strrpos($parent, '\\')) !== $namespace) {
		$imports[] = $parent;
	}

	$imports = array_unique($imports); // a slot may hold the parent class too
	sort($imports);
	$lines = ['<?php declare(strict_types=1);', '', "namespace $namespace;", ''];
	foreach ($imports as $import) {
		$lines[] = "use $import;";
	}

	$lines[] = '';
	$lines[] = '';
	$lines[] = '/**';
	$lines[] = ' * @todo describe the node';
	$lines[] = ' */';
	$lines[] = "final class $shortName extends $parentShort";
	$lines[] = '{';
	$lines[] = $constant;
	$lines[] = '';
	$lines[] = '';
	$lines[] = $constructor;
	$lines[] = '}';
	$lines[] = '';
	return implode("\n", $lines);
}


const LayoutRoles = ['Content', 'Anchor', 'Closes', 'Body', 'Operator', 'Branch', 'Link', 'Case'];


/**
 * The LayoutRole of every slot of every node: the convention by the name of the slot, and the layout entry
 * of the schema where it says otherwise.
 * @param array<string, array{slots: array<string, string>, layout?: array<string, string>}> $schema
 */
function renderLayoutData(array $schema): string
{
	$entries = [];
	foreach ($schema as $class => $definition) {
		$layout = $definition['layout'] ?? [];
		foreach ($layout as $slot => $role) {
			if (!isset($definition['slots'][$slot])) {
				throw new Exception("$class: the layout names a slot $slot the node does not have");
			} elseif (!in_array($role, LayoutRoles, strict: true)) {
				throw new Exception("$class: unknown layout role $role of the slot $slot");
			}
		}

		$slots = [];
		foreach (array_keys($definition['slots']) as $slot) {
			$role = $layout[$slot] ?? layoutRoleOf($slot);
			$slots[] = "\t\t\t'$slot' => LayoutRole::$role,";
		}

		$entries[] = "\t\tNodes\\$class::class => [" . ($slots ? "\n" . implode("\n", $slots) . "\n\t\t" : '') . '],';
	}

	$body = implode("\n", $entries);
	return <<<PHP
		<?php declare(strict_types=1);

		/**
		 * @generated by grammar/build.php from grammar/nodes.php, do not edit.
		 */

		namespace PhpSyntax;


		/**
		 * The role every slot of every node plays in the indentation of the lines the node spreads over.
		 */
		final class LayoutData
		{
			/** @var array<class-string<Node>, array<string, LayoutRole>> */
			public const Roles = [
		$body
			];
		}

		PHP;
}


/** The role a slot has by its name: what closes, what stands with the construct, its body, and its content. */
function layoutRoleOf(string $slot): string
{
	return match (true) {
		str_starts_with($slot, 'close'), $slot === 'endKeyword' => 'Closes',
		str_starts_with($slot, 'open'), str_ends_with($slot, 'Keyword'), $slot === 'modifiers', $slot === 'attributes', $slot === 'semicolon' => 'Anchor',
		$slot === 'body' => 'Body',
		default => 'Content',
	};
}
