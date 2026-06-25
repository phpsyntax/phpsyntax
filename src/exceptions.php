<?php declare(strict_types=1);

namespace PhpSyntax;


/**
 * The source code is not valid PHP; where in the source is told apart from where the exception was raised,
 * which is what getLine() and getFile() say.
 */
final class ParseException extends \Exception
{
	/** Column the error stands in, 1-based, in characters; null where the position is unknown. */
	public readonly ?int $sourceColumn;


	public function __construct(
		string $message,
		/** Line of the source the error stands on, 1-based */
		public readonly ?int $sourceLine = null,
		/** Byte offset of the error in the source */
		public readonly ?int $sourceOffset = null,
		/** the source itself, which the column is counted in; it is not kept */
		?string $code = null,
	) {
		parent::__construct($message);
		$this->sourceColumn = $code === null || $sourceOffset === null
			? null
			: mb_strlen((string) preg_replace('~^.*[\r\n]~s', '', substr($code, 0, $sourceOffset))) + 1;
	}
}
