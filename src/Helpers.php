<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;


/**
 * Small operations that belong to no class of their own.
 * @internal
 */
final class Helpers
{
	/**
	 * Returns the list with `$remove` items at `$index` replaced by `$insert`.
	 * @template U
	 * @param  list<U>  $list
	 * @param  list<U>  $insert
	 * @return list<U>
	 */
	public static function spliceList(array $list, int $index, int $remove, array $insert = []): array
	{
		return [...array_slice($list, 0, $index), ...$insert, ...array_slice($list, $index + $remove)];
	}


	/**
	 * The code as a code span of Markdown, as a message writes it, in a run of backticks longer than any it holds;
	 * an empty one, which Markdown cannot mark, as the empty string of PHP.
	 */
	public static function formatCode(string $code): string
	{
		if ($code === '') {
			return "`''`";
		}

		preg_match_all('~`+~', $code, $m);
		$fence = str_repeat('`', max([0, ...array_map(strlen(...), $m[0])]) + 1);
		$pad = str_starts_with($code, '`') || str_ends_with($code, '`') ? ' ' : '';
		return $fence . $pad . $code . $pad . $fence;
	}
}
