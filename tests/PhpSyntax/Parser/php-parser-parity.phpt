<?php declare(strict_types=1);

/**
 * The expressions of the tree stand at the offsets nikic/php-parser gives its expressions, over the committed
 * corpus and, when PHPSYNTAX_CORPUS points to a directory, over that tree. The grammar is a fork of the
 * grammar of php-parser and this is what a tool bringing positions computed over its AST (a type from
 * PHPStan) relies on; a difference here is a difference of the grammars, not of the tool.
 */

use PhpParser\{Node as ParserNode, NodeTraverser, NodeVisitorAbstract, ParserFactory};
use PhpSyntax\{Node, ParseException, Parser};
use PhpSyntax\Nodes\Expression\ParenthesizedNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\Scalar\{HeredocNode, InterpolatedStringNode};
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


/** @return list<string> */
function findFiles(string $dir): array
{
	$files = [];
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file) {
		if (preg_match('~\.(php|phpt|inc)$~', $file->getFilename())) {
			$files[] = $file->getPathname();
		}
	}

	sort($files);
	return $files;
}


/**
 * Expressions of php-parser with their offsets, the end exclusive, without the ones the tree has no
 * expression for: a parenthesized expression is the parentheses in the tree and the inner expression alone
 * in php-parser, so the inner one is what both have.
 */
final class ExpressionCollector extends NodeVisitorAbstract
{
	/** @var list<array{int, int, string}> */
	public array $expressions = [];


	public function enterNode(ParserNode $node): ?int
	{
		if ($node instanceof ParserNode\Expr\List_) { // a pattern of a destructuring, a ListNode in the tree
			return null;
		} elseif ($node instanceof ParserNode\Expr && !$node instanceof ParserNode\Expr\Error) {
			$this->expressions[] = [$node->getStartFilePos(), $node->getEndFilePos() + 1, $node::class];
		}

		// what is written inside a string each of them models in its own way; the string itself is one expression
		return $node instanceof ParserNode\Scalar\InterpolatedString ? NodeTraverser::DONT_TRAVERSE_CHILDREN : null;
	}
}


/** Whether the expression is written inside a string. */
function isInterpolated(Node $node): bool
{
	for ($parent = $node->parent; $parent !== null; $parent = $parent->parent) {
		if ($parent instanceof InterpolatedStringNode || $parent instanceof HeredocNode) {
			return true;
		}
	}

	return false;
}


/**
 * @param  list<string>  $files
 * @return list<string>  failures
 */
function compareExpressions(array $files): array
{
	$parser = new Parser;
	$phpParser = (new ParserFactory)->createForNewestSupportedVersion();
	$failures = [];
	foreach ($files as $file) {
		$code = (string) file_get_contents($file);
		try {
			$tree = $parser->parse($code);
			$ast = $phpParser->parse($code) ?? [];
		} catch (ParseException|PhpParser\Error) {
			continue; // the round-trip test judges what does not parse
		}

		$collector = new ExpressionCollector;
		new NodeTraverser($collector)->traverse($ast);
		$index = $tree->getIndex();
		$seen = [];
		foreach ($collector->expressions as [$start, $end, $class]) {
			$seen["$start:$end"] = true;
			if ($index->findNode($start, $end, ExpressionNode::class) === null) {
				$failures[] = "$file: no expression at $start-$end for $class '" . substr($code, $start, $end - $start) . "'";
			}
		}

		foreach ($tree->find(ExpressionNode::class) as $expression) {
			[$start, $end] = $index->getOffsetRange($expression) ?? [0, 0];
			if (
				!$expression instanceof ParenthesizedNode
				&& !isInterpolated($expression)
				&& !isset($seen["$start:$end"])
			) {
				$failures[] = "$file: php-parser has no expression at $start-$end for " . $expression::class . " '" . substr($code, $start, $end - $start) . "'";
			}
		}
	}

	return $failures;
}


test('committed corpus', function () {
	$files = findFiles(__DIR__ . '/../../corpus');
	Assert::true(count($files) > 100);
	Assert::same([], compareExpressions($files));
});


$external = getenv('PHPSYNTAX_CORPUS');
if ($external !== false) {
	test('external corpus', function () use ($external) {
		Assert::true(is_dir($external), "PHPSYNTAX_CORPUS '$external' is not a directory");
		$files = findFiles($external);
		echo count($files), " files\n";
		Assert::same([], compareExpressions($files));
	});
}
