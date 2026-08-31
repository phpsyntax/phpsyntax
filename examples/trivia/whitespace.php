<?php declare(strict_types=1);

/**
 * Whitespace as data: reading the space between two tokens, writing it, and the rules that
 * keep a file consistent instead of merely changed.
 *
 * Demonstrates: `Token::getTrailingSpace()`, `setTrailingSpace()`, `setBlankLinesBefore()`,
 *               `ensureStartsLine()`, `getIndentation()`, `getLineIndentation()`, Style,
 *               `Indentation::infer()`, `Indentation::shift()`
 * Usage:        php examples/trivia/whitespace.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\{Indentation, Parser, Style};
use PhpSyntax\Nodes\Expression\BinaryOpNode;
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Statement\ClassNode;

$code = sample(<<<'PHP'
	<?php
	class Report
	{
		public function rows(): array { return $this->rows; }
			public function count(): int
			{
				$this->log(<<<TXT
					counting the rows
					TXT);
				return count($this->rows)+1;
			}
	}
	PHP);

$file = new Parser()->parse($code);

// the space between two tokens belongs to the first of them
$plus = $file->findFirst(BinaryOpNode::class, fn(BinaryOpNode $node) => $node->operator->is('+'));
$before = $plus->left->getLastToken();
echo 'space before "+": ', json_encode($before->getTrailingSpace()), "\n";
echo 'space after "+": ', json_encode($plus->operator->getTrailingSpace()), "\n";

// null means a line ending, a comment or string content is in the way, so there is no plain
// space to read and setTrailingSpace() would refuse to write one
$brace = $file->findFirst(ClassNode::class)->openBrace;
echo 'space after the class brace: ', json_encode($brace->getTrailingSpace()), "\n";

// indentation is a property of a line, so a token in the middle of one has none of its own;
// getLineIndentation() walks back to the token that starts the line and answers for both
$methods = $file->find(MethodNode::class);
$return = $methods[1]->body->statements[1]->getFirstToken();
echo 'indentation of "return": ', json_encode($return->getIndentation()), "\n";
echo 'indentation of "+": ', json_encode($plus->operator->getIndentation()), "\n";
echo 'line indentation of "+": ', json_encode($plus->operator->getLineIndentation()), "\n\n";

// writing it: one operator, spaced
$before->setTrailingSpace(' ');
$plus->operator->setTrailingSpace(' ');

// the line ending comes from the file being edited, not from the tool doing the editing
$style = new Style(indent: "\t", lineEnding: Style::detectLineEnding($code));

// a blank line between the two methods
$methods[1]->getFirstToken()->setBlankLinesBefore(1, $style->lineEnding);

// and the one-liner broken onto its own lines: each token starts a line and gets the indentation
// its place in the tree gives it, the braces level with the method and the statement one deeper
$body = $methods[0]->body;
$statement = $body->statements[0]->getFirstToken();
foreach ([$body->openBrace, $statement, $body->closeBrace] as $token) {
	$token->ensureStartsLine($style->lineEnding);
	$token->setIndentation(Indentation::infer($token, $style));
}

// the second method came indented one level too deep, and a whole construct moves in one call:
// every line the node opens, and with a heredoc its body and closing delimiter too, so the value
// it stands for does not change
$printed = (string) $file;
Indentation::shift($methods[1], -1, $style);

echo "--- the method put back where it belongs ---\n";
printDiff($printed, (string) $file);

echo "\n", (string) $file, "\n";
