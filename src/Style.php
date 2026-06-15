<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;

use function strlen;


/**
 * Whitespace conventions of a file: the indentation unit, the line ending and the width of a tab.
 */
final readonly class Style
{
	public function __construct(
		public string $indent = "\t",
		public string $lineEnding = "\n",
		public int $tabWidth = 4,
	) {
		if ($indent === '' || strspn($indent, " \t") !== strlen($indent)) {
			throw new \InvalidArgumentException(Helpers::formatCode($indent) . ' is not an indentation unit, which is made of spaces and tabs.');
		} elseif ($tabWidth < 1) {
			throw new \InvalidArgumentException("Tab width `$tabWidth` is not positive.");
		}

		Helpers::checkLineEnding($lineEnding);
	}


	/**
	 * Returns the prevailing line ending of the code, a lone `"\r"` included; `"\n"` when there is none, and the one
	 * listed first in `"\n"`, `"\r\n"`, `"\r"` when the counts are equal.
	 */
	public static function detectLineEnding(string $code): string
	{
		$crlf = substr_count($code, "\r\n");
		$counts = ["\n" => substr_count($code, "\n") - $crlf, "\r\n" => $crlf, "\r" => substr_count($code, "\r") - $crlf];
		return (string) array_search(max($counts), $counts, strict: true);
	}


	public function withLineEnding(string $lineEnding): self
	{
		return new self($this->indent, $lineEnding, $this->tabWidth);
	}


	public function withIndent(string $indent): self
	{
		return new self($indent, $this->lineEnding, $this->tabWidth);
	}


	/**
	 * Returns the indentation repeated for the level.
	 */
	public function indent(int $level): string
	{
		return str_repeat($this->indent, $level);
	}
}
