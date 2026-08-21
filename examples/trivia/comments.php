<?php declare(strict_types=1);

/**
 * Comments: where they live, how to read them, and how to ask whether a change would destroy one.
 *
 * Demonstrates: Node::getDocComment(), getComments(), hasComment(), Token::hasCommentUpTo(),
 *               Trivia::getCommentText(), Token::removeTrivia()
 * Usage:        php examples/trivia/comments.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Parser;
use PhpSyntax\Trivia;

$code = sample(<<<'PHP'
	<?php
	class Cart
	{
		/**
		 * Total price including VAT.
		 * @param  list<Item>  $items
		 */
		public function total(array $items): float
		{
			// rounding: the invoice is what the customer pays
			return round($this->sum($items) * 1.21, 2); # legacy hash comment
		}
	}
	PHP);

$file = new Parser()->parse($code);
$method = $file->find(MethodNode::class)[0];
$body = must($method->body);

// the doc comment of a node, wherever it sits: above the node or after the previous token
echo "doc comment text:\n", $method->getDocComment()?->getCommentText(), "\n\n";

// every comment inside the node, its outer edges excluded
foreach ($method->getComments() as $comment) {
	echo 'inside the method: ', $comment->kind->name, ' ', json_encode($comment->text, JSON_UNESCAPED_SLASHES), "\n";
}

// "would this change destroy a comment?" is one call, not a traversal you write yourself
$class = $file->find(ClassNode::class)[0];
$return = $body->statements->getItems()[0];
echo "\n", 'the class body holds a comment: ', var_export($class->hasComment(), return: true), "\n";
echo 'between "return" and the closing brace: ', var_export(
	must($return->getFirstToken())->hasCommentUpTo(must($body->getLastToken())),
	return: true,
), "\n";

// removing one tidies the whitespace around it: alone on its line, it takes the line with it.
// The node finds the token the trivia hangs on, so a comment goes away where it was found.
$lineComment = array_find($method->getComments(), fn(Trivia $trivia) => $trivia->isLineComment());
$method->removeTrivia(must($lineComment));

echo "\n";
printDiff($code, (string) $file);
