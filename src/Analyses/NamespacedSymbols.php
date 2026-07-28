<?php declare(strict_types=1);

namespace PhpSyntax\Analyses;

use function strlen;


/**
 * The functions and constants the namespaces declare outside the file, and whether the list is complete.
 */
final readonly class NamespacedSymbols
{
	/** @var array<string, true>  lowercased fully qualified names */
	private array $functions;

	/** @var array<string, true>  fully qualified names with the namespace lowercased */
	private array $constants;


	/**
	 * @param  list<string>  $functions  fully qualified names
	 * @param  list<string>  $constants  fully qualified names
	 */
	public function __construct(
		array $functions = [],
		array $constants = [],
		/** a name missing from the lists is global for certain */
		public bool $complete = false,
	) {
		$this->functions = array_fill_keys(array_map(fn(string $name) => strtolower(self::normalize($name)), $functions), true);
		$this->constants = array_fill_keys(array_map(fn(string $name) => self::toConstantKey(self::normalize($name)), $constants), true);
	}


	/** Whether a namespace declares the function, given its fully qualified name. */
	public function hasFunction(string $fullName): bool
	{
		return isset($this->functions[strtolower(ltrim($fullName, '\\'))]);
	}


	/** Whether a namespace declares the constant, given its fully qualified name; the name itself is case-sensitive. */
	public function hasConstant(string $fullName): bool
	{
		return isset($this->constants[self::toConstantKey(ltrim($fullName, '\\'))]);
	}


	private static function normalize(string $name): string
	{
		$name = ltrim($name, '\\');
		$pos = strrpos($name, '\\');
		return $pos === false || $pos === strlen($name) - 1
			? throw new \InvalidArgumentException("'$name' is not the fully qualified name of a symbol in a namespace.")
			: $name;
	}


	private static function toConstantKey(string $name): string
	{
		$pos = (int) strrpos($name, '\\');
		return strtolower(substr($name, 0, $pos)) . substr($name, $pos);
	}
}
