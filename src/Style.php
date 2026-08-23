<?php declare(strict_types=1);

namespace PhpSyntax;


/**
 * Whitespace conventions of a file: the indentation unit, the line ending and the width of a tab.
 */
final readonly class Style
{
	public function __construct(
		public string $indent = "\t",
		public string $eol = "\n",
		public int $tabWidth = 4,
	) {
	}


	/**
	 * Returns the prevailing line ending of the code; "\n" when there is none or the counts are equal.
	 */
	public static function detectEol(string $code): string
	{
		$crlf = substr_count($code, "\r\n");
		$lf = substr_count($code, "\n") - $crlf;
		return $crlf > $lf ? "\r\n" : "\n";
	}


	public function withEol(string $eol): self
	{
		return new self($this->indent, $eol, $this->tabWidth);
	}


	public function withIndent(string $indent): self
	{
		return new self($indent, $this->eol, $this->tabWidth);
	}


	/**
	 * Returns the indentation repeated for the level.
	 */
	public function indent(int $level): string
	{
		return str_repeat($this->indent, $level);
	}
}
