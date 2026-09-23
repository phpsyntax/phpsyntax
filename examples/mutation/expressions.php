<?php declare(strict_types=1);

/**
 * Lifting an expression out of the call that wrapped it: the parentheses its new place needs, and
 * the space that keeps two tokens from being read as one.
 *
 * Demonstrates: `ExpressionNode::replaceWithExpression()`, `Node::withoutEdgeTrivia()`, `Lexer::canAdjoin()`
 * Usage:        php examples/mutation/expressions.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Lexer\Lexer;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	function label(array $row): string
	{
		$name = legacy_str($row['name'] ?? 'anonymous');

		$title = legacy_str($row['title'] ?? '') . ' - ' . $name;

		$tag = 'v'.legacy_str(119);

		return $title . $tag;
	}
	PHP);


/**
 * Drops every `legacy_str()` call in the file and hands its argument to the write, as a copy
 * without the trivia of the place it stood in.
 * @param  callable(FunctionCallNode, ExpressionNode): void  $write
 */
function unwrapCalls(FileNode $file, callable $write): void
{
	foreach ($file->find(FunctionCallNode::class) as $call) {
		if ($call->name instanceof NameNode && $call->name->equals('legacy_str')) {
			$argument = must($call->arguments->findArgument('value', 0));
			$write($call, $argument->value->withoutEdgeTrivia());
		}
	}
}


$parser = new Parser;

// replaceWith() writes what it is given, so the operators around the old call get a new operand
$verbatim = $parser->parse($code);
unwrapCalls($verbatim, fn(FunctionCallNode $call, ExpressionNode $value) => $call->replaceWith($value));
echo "replaceWith()\n";
printDiff($code, (string) $verbatim);

// replaceWithExpression() keeps the parentheses wherever isRedundant() does not call them needless
$file = $parser->parse($code);
unwrapCalls($file, fn(FunctionCallNode $call, ExpressionNode $value) => $call->replaceWithExpression($value));
echo "\nreplaceWithExpression()\n";
printDiff($code, (string) $file);

// the seam: which pairs of tokens the lexer would read as one thing, and so may not be written together
echo "\nleft     right   together   written as\n";
$lexer = new Lexer;
foreach ([['.', '119'], ['.', "'x'"], ['-', '-'], ['return', 'FOO'], [']', '[']] as [$left, $right]) {
	$adjoins = $lexer->canAdjoin($left, $right);
	printf("%-8s %-7s %-10s %s\n", $left, $right, $adjoins ? 'yes' : 'no', $adjoins ? "$left$right" : "$left $right");
}
