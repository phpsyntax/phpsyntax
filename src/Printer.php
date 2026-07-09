<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;


/**
 * Prints a tree back to source code: every token with its trivia, in the order of the children. A substitute
 * given prints in place of the text of a token, the trivia staying as they are, which is how a tool shows the
 * code with changes it has not made to the tree.
 */
final class Printer
{
	/**
	 * @param  ?callable(Token): ?string  $substitute  the text to print for the token; null keeps its own
	 */
	public static function print(Node|Token $node, ?callable $substitute = null): string
	{
		if ($node instanceof Token) {
			return self::printTrivia($node->leadingTrivia)
				. self::printToken($node, $substitute)
				. self::printTrivia($node->trailingTrivia);
		}

		$code = '';
		foreach ($node->getChildren() as $child) {
			$code .= self::print($child, $substitute);
		}

		return $code;
	}


	/**
	 * Prints the node as it is written, without the trivia on its outer edges, which `Node::$text` reads.
	 * @param  ?callable(Token): ?string  $substitute  the text to print for the token; null keeps its own
	 */
	public static function printText(Node $node, ?callable $substitute = null): string
	{
		$text = '';
		$previous = null;
		foreach ($node->getTokens() as $token) {
			if ($previous !== null) { // what stands between two tokens, so the edges never come up
				$text .= self::printTrivia($previous->trailingTrivia) . self::printTrivia($token->leadingTrivia);
			}

			$text .= self::printToken($token, $substitute);
			$previous = $token;
		}

		return $text;
	}


	/** @param  ?callable(Token): ?string  $substitute */
	private static function printToken(Token $token, ?callable $substitute): string
	{
		return $substitute === null
			? $token->text
			: $substitute($token) ?? $token->text;
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
