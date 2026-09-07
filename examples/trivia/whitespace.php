<?php declare(strict_types=1);

/**
 * Whitespace as data: reading the space between two tokens, writing it, and the rules that
 * keep a file consistent instead of merely changed.
 *
 * Demonstrates: Token::getTrailingSpace(), setTrailingSpace(), setBlankLinesBefore(),
 *               ensureLeadingNewline(), getIndentation(), getLineIndentation(), Style,
 *               Indentation::shift()
 * Usage:        php examples/trivia/whitespace.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Indentation;
use PhpSyntax\Nodes\Expression\BinaryOpNode;
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Parser;
use PhpSyntax\Style;

$code = sample(<<<'PHP'
	<?php
	class Report
	{
		public function rows(): array { return $this->rows; }
			public function count(): int
			{
				return count($this->rows)+1;
			}
	}
	PHP);

$file = new Parser()->parse($code);

// the space between two tokens belongs to the first of them
$plus = must($file->findFirst(BinaryOpNode::class, fn(BinaryOpNode $node) => $node->operator->is('+')));
$before = must($plus->left->getLastToken());
echo 'space before "+": ', json_encode($before->getTrailingSpace()), "\n";
echo 'space after "+": ', json_encode($plus->operator->getTrailingSpace()), "\n";

// null means a line ending, a comment or string content is in the way, so there is no plain
// space to read and setTrailingSpace() would refuse to write one
$brace = $file->find(ClassNode::class)[0]->openBrace;
echo 'space after the class brace: ', json_encode($brace->getTrailingSpace()), "\n";

// indentation is a property of a line, so a token in the middle of one has none of its own;
// getLineIndentation() walks back to the token that starts the line and answers for both
$return = must($file->find(MethodNode::class)[1]->body?->statements->getItems()[0]->getFirstToken());
echo 'indentation of "return": ', json_encode($return->getIndentation()), "\n";
echo 'indentation of "+": ', json_encode($plus->operator->getIndentation()), "\n";
echo 'line indentation of "+": ', json_encode($plus->operator->getLineIndentation()), "\n\n";

// writing it: one operator, spaced
$before->setTrailingSpace(' ');
$plus->operator->setTrailingSpace(' ');

// the line ending comes from the file being edited, not from the tool doing the editing
$style = new Style(indent: "\t", eol: Style::detectEol($code));

// a blank line between the two methods
$methods = $file->find(MethodNode::class);
must($methods[1]->getFirstToken())->setBlankLinesBefore(1, $style->eol);

// and the one-liner broken onto its own lines: each token starts a line and gets its indentation
$body = must($methods[0]->body);
$indentation = must($methods[0]->getFirstToken())->getLineIndentation();
$statement = must($body->statements->getItems()[0]->getFirstToken());

$body->openBrace->ensureLeadingNewline($style->eol);
$body->openBrace->setIndentation($indentation);
$statement->ensureLeadingNewline($style->eol);
$statement->setIndentation($indentation . $style->indent);
$body->closeBrace->ensureLeadingNewline($style->eol);
$body->closeBrace->setIndentation($indentation);

// the second method came indented one level too deep, and a whole construct moves in one call:
// every line the node opens, and with a heredoc its body and closing delimiter too, so the value
// it stands for does not change
$printed = (string) $file;
Indentation::shift($methods[1], -1, $style);

echo "--- the method put back where it belongs ---\n";
printDiff($printed, (string) $file);

echo "\n", (string) $file, "\n";
