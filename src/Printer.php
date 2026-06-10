<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;


/**
 * Prints a tree back to source code: every token with its trivia, in the order of the children.
 */
final class Printer
{
	public static function print(Node|Token $node): string
	{
		if ($node instanceof Token) {
			return (string) $node;
		}

		$code = '';
		foreach ($node->getChildren() as $child) {
			$code .= self::print($child);
		}

		return $code;
	}


	/**
	 * Prints the node as it is written, without the trivia on its outer edges, which `Node::$text` reads.
	 */
	public static function printText(Node $node): string
	{
		$text = '';
		$previous = null;
		foreach ($node->getTokens() as $token) {
			if ($previous !== null) { // what stands between two tokens, so the edges never come up
				$text .= self::printTrivia($previous->trailingTrivia) . self::printTrivia($token->leadingTrivia);
			}

			$text .= $token->text;
			$previous = $token;
		}

		return $text;
	}


	/** @param  list<Trivia>  $trivia */
	private static function printTrivia(array $trivia): string
	{
		$text = '';
		foreach ($trivia as $item) {
			$text .= $item->text;
		}

		return $text;
	}
}
