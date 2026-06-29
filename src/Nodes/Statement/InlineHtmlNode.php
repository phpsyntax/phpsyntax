<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Statement;

use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Token;


/**
 * Text outside PHP tags, including a BOM or a hashbang line.
 */
final class InlineHtmlNode extends StatementNode
{
	public const Slots = ['html'];


	/** @internal */
	public function __construct(
		public Token $html { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/**
	 * Whether the text is only what may precede the code of a pure PHP file: a byte order mark, a hashbang
	 * line, or both.
	 */
	public function isPreamble(): bool
	{
		return preg_match("~^(\xEF\xBB\xBF)?(#![^\r\n]*\\R)?$~", $this->html->text) === 1;
	}
}
