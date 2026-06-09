<?php declare(strict_types=1);

use PhpSyntax\{Node, Token, TokenKind, Trivia};
use PhpSyntax\Nodes\{ModifiersNode, NodeList, SeparatedNodeList};


/**
 * Renders a tree as indented text: node classes with their slots, tokens with kind, text and trivia.
 */
final class Dumper
{
	public static function dump(Node|Token $node): string
	{
		return self::dumpNode($node, '');
	}


	private static function dumpNode(Node|Token $node, string $indent): string
	{
		if ($node instanceof Token) {
			return self::dumpToken($node) . "\n";
		}

		$class = $node::class;
		$output = substr($class, strrpos($class, '\\') + 1) . "\n";
		if (
			$node instanceof NodeList
			|| $node instanceof SeparatedNodeList
			|| $node instanceof ModifiersNode
		) {
			foreach ($node->getChildren() as $child) {
				$output .= "$indent\t- " . self::dumpNode($child, "$indent\t");
			}

			return $output;
		}

		foreach ($node::Slots as $slot) {
			if ($node->$slot !== null) {
				$output .= "$indent\t$slot: " . self::dumpNode($node->$slot, "$indent\t");
			}
		}

		return $output;
	}


	private static function dumpToken(Token $token): string
	{
		$output = (TokenKind::Names[$token->kind] ?? "'$token->text'") . ' ' . self::quote($token->text);
		foreach (['leadingTrivia' => '<', 'trailingTrivia' => '>'] as $property => $mark) {
			if ($token->$property) {
				$output .= '  ' . $mark . implode(' ', array_map(
					fn(Trivia $trivia) => $trivia->kind->name . ($trivia->inInterpolation ? '*' : '') . self::quote($trivia->text),
					$token->$property,
				));
			}
		}

		return $output;
	}


	private static function quote(string $text): string
	{
		return (string) json_encode($text, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
}
