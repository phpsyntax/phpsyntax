<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax\Nodes\Scalar;

use PhpSyntax\{Escaping, Node, Token};
use PhpSyntax\Nodes\{ExpressionNode, NodeList, ScalarNode};
use function strlen;


/**
 * Heredoc or nowdoc; the closing delimiter keeps its indentation, the parts keep theirs.
 */
final class HeredocNode extends ScalarNode
{
	public const Slots = ['openDelimiter', 'parts', 'closeDelimiter'];

	/** The label between <<< and the body, without quotes. */
	public string $label {
		get => trim(substr($this->openDelimiter->text, strlen('<<<')), " \t\r\n'\"");
	}

	/** The indentation of the closing delimiter, which the body shares. */
	public string $indentation {
		get => substr($this->closeDelimiter->text, 0, strspn($this->closeDelimiter->text, " \t"));
	}

	/**
	 * The text of the body with its escape sequences resolved and the common indentation removed.
	 * @throws \LogicException  when the heredoc interpolates; `hasInterpolation()` tells beforehand
	 */
	public string $value {
		get {
			$body = '';
			foreach ($this->parts->getItems() as $part) {
				if (!$part instanceof InterpolatedStringPartNode) {
					throw new \LogicException('The heredoc interpolates, so it has no value of its own.');
				}

				$body .= $part->token->text;
			}

			// the line ending before the closing delimiter ends the heredoc, it is not part of the value
			$body = (string) preg_replace('~\r\n$|[\r\n]$~', '', $body);
			// the indentation is physical, so it goes before the escapes are read: \n makes no line to outdent
			$body = self::outdent($body, $this->indentation);
			// a heredoc resolves the escapes of a double-quoted string, the double quote itself excepted
			return $this->isNowdoc() ? $body : Escaping::decode($body, quote: null);
		}
	}


	/** @internal */
	public function __construct(
		public Token $openDelimiter { set => $this->prepareSlot(__PROPERTY__, $value); },
		/** @var NodeList<InterpolatedStringPartNode|InterpolationNode|ExpressionNode> */
		public NodeList $parts { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeDelimiter { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/** Whether the label is in single quotes, which makes a nowdoc that resolves no escape sequences. */
	public function isNowdoc(): bool
	{
		return str_contains($this->openDelimiter->text, "'");
	}


	/** Whether any part of the body is an interpolation, which leaves the heredoc without a value of its own. */
	public function hasInterpolation(): bool
	{
		return array_any($this->parts->getItems(), fn(Node $part) => !$part instanceof InterpolatedStringPartNode);
	}


	/**
	 * Removes the indentation of the closing delimiter from the beginning of every line; a line of nothing
	 * but whitespace may be shorter than that and loses what it has, the way PHP reads it.
	 */
	private static function outdent(string $body, string $indentation): string
	{
		if ($indentation === '') {
			return $body;
		}

		$lines = preg_split('~(\r\n|\r|\n)~', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
		foreach ($lines as $i => $line) {
			if ($i % 2 === 1) {
				continue;
			}

			$lines[$i] = match (true) {
				str_starts_with($line, $indentation) => substr($line, strlen($indentation)),
				trim($line, " \t") === '' => '',
				default => $line,
			};
		}

		return implode('', $lines);
	}
}
