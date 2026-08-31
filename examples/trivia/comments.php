<?php declare(strict_types=1);

/**
 * Comments: where they live, how to read them, and how to ask whether a change would destroy one.
 *
 * Demonstrates: `Node::getDocComment()`, `getInnerComments()`, `hasInnerComment()`, `Token::hasCommentUpTo()`,
 *               `getLeadingComments()`, `getTrailingComments()`, `hasLeadingComment()`, `hasTrailingComment()`,
 *               `Trivia::getCommentText()`, `Node::removeTrivia()`
 * Usage:        php examples/trivia/comments.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\{Parser, Trivia};

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
			return round($this->sum($items) * 1.21 /* VAT */, 2); # legacy hash comment
		}
	}
	PHP);

$file = new Parser()->parse($code);
$method = $file->findFirst(MethodNode::class);
$body = $method->body;

// the doc comment of a node, wherever it sits: above the node or after the previous token
echo "doc comment text:\n", $method->getDocComment()->getCommentText(), "\n\n";

// every comment inside the node, its outer edges excluded
foreach ($method->getInnerComments() as $comment) {
	echo 'inside the method: ', $comment->getTokenName(), ' ', json_encode($comment->text, JSON_UNESCAPED_SLASHES), "\n";
}

// "would this change destroy a comment?" is one call, not a traversal you write yourself
$class = $file->findFirst(ClassNode::class);
$return = $body->statements[0];
echo "\n", 'the class body holds a comment: ', var_export($class->hasInnerComment(), return: true), "\n";
echo 'between "return" and the closing brace: ', var_export(
	$return->getFirstToken()->hasCommentUpTo($body->getLastToken()),
	return: true,
), "\n";

// a comment is inside a node, before it or after it, and each tool asks about its own place: a rewrite
// of the statement would destroy what is inside, a tool moving it has to carry what is around it
$comments = fn(array $trivia) => implode(', ', array_map(fn(Trivia $comment) => $comment->text, $trivia));
echo "\nthe return statement:\n";
echo '  inside:   ', $comments($return->getInnerComments()), "\n";
echo '  before:   ', $comments($return->getLeadingComments()), "\n";
echo '  after:    ', $comments($return->getTrailingComments()), "\n";
echo '  a rewrite of it is safe: ', var_export(!$return->hasInnerComment(), return: true), "\n";
echo '  moving it moves a comment: ', var_export($return->hasLeadingComment() || $return->hasTrailingComment(), return: true), "\n";

// removing one tidies the whitespace around it: alone on its line, it takes the line with it, within
// a line one adjacent space. The node finds the token the trivia hangs on, so a comment goes away where
// it was found.
$lineComment = array_find($method->getInnerComments(), fn(Trivia $trivia) => $trivia->isLineComment());
$method->removeTrivia($lineComment);
$vatComment = array_find($method->getInnerComments(), fn(Trivia $trivia) => $trivia->text === '/* VAT */');
$method->removeTrivia($vatComment);

echo "\n";
printDiff($code, (string) $file);
