<?php declare(strict_types=1);

/**
 * A complete codemod: migrate calls of a deprecated function to the method that replaced it,
 * report what could not be migrated, and leave the rest of the file alone.
 *
 * Demonstrates: `find()` + NameResolver + node factories + `hasComment()`, all together
 * Usage:        php examples/codemod/deprecated-api.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\Nodes\ArgumentListNode;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Expression\MethodCallNode;
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	namespace App\Legacy;

	class Report
	{
		public function rows(Database $db, int $limit, array $params): array
		{
			// the reporting query, do not touch the ORDER BY
			$rows = db_query($db, 'SELECT * FROM orders ORDER BY id DESC');

			$page = db_query(
				$db,
				'SELECT * FROM orders LIMIT ' . $limit,
			);

			$count = db_query($db);
			$named = db_query(query: 'SELECT 1', connection: $db);
			$all = db_query(...$params);
			$audit = db_query($db, /* the slow one */ 'SELECT * FROM audit');

			return [$rows, $page, $count, $named, $all, $audit];
		}
	}
	PHP);

$parser = new Parser;
$file = $parser->parse($code);
$resolver = new NameResolver($file);

$migrated = 0;
foreach ($file->find(FunctionCallNode::class) as $call) {
	if (!$resolver->isGlobalFunctionCall($call, 'db_query')) {
		continue;
	}

	// the two arguments the method takes, written by position or by name; null where the call
	// does not say which argument a parameter gets, and a call holding anything else is left alone
	$connection = $call->arguments->findArgument('connection', 0);
	$query = $call->arguments->findArgument('query', 1);

	// originalLine, not getStartLine(): the report is about the file on disk, and earlier
	// rewrites have already moved the lines of the tree
	$line = must($call->getFirstToken())->originalLine;
	if ($connection === null || $query === null || count($call->arguments->items) !== 2) {
		echo "line $line: left alone, the call does not give the two arguments the method takes\n";
		continue;
	} elseif ($call->hasComment()) {
		// the rewrite joins the call onto one line, and a comment between the arguments would
		// either move or swallow the code after it
		echo "line $line: left alone, there is a comment inside the call\n";
		continue;
	}

	// the method call is built from the two expressions the old call already holds, which no text
	// template could carry. They go in as withoutEdgeTrivia() copies, because a factory takes no node
	// that still stands in the file; the whitespace on their edges it drops itself, the new place has
	// its own in the tokens around it.
	$call->replaceWith(MethodCallNode::of(
		$connection->value->withoutEdgeTrivia(),
		'query',
		ArgumentListNode::of($query->value->withoutEdgeTrivia()),
	));

	$migrated++;
}

echo "$migrated calls migrated\n\n";
printDiff($code, (string) $file);
