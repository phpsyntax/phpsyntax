<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;


/**
 * The source code is not valid PHP; where in the source is told apart from where the exception was raised,
 * which is what `getLine()` and `getFile()` say.
 */
final class ParseException extends \Exception
{
	/** Line of the source the error stands on, 1-based; null where the position is unknown. */
	public readonly ?int $sourceLine;

	/** Column the error stands in, 1-based, in characters; null where the position is unknown. */
	public readonly ?int $sourceColumn;


	public function __construct(
		string $message,
		/** Line of the source the error stands on, 1-based; counted from the code where not given */
		?int $sourceLine = null,
		/** Byte offset of the error in the source */
		public readonly ?int $sourceOffset = null,
		/** The source itself, which the line and the column are counted in; it is not kept */
		?string $code = null,
	) {
		parent::__construct($message);
		$before = $code === null || $sourceOffset === null ? null : substr($code, 0, $sourceOffset);
		$this->sourceLine = $sourceLine ?? ($before === null ? null : preg_match_all('~\r\n|\r|\n~', $before) + 1);
		$this->sourceColumn = $before === null
			? null
			: TokenIndex::countCharacters((string) preg_replace('~^.*[\r\n]~s', '', $before)) + 1;
	}
}
