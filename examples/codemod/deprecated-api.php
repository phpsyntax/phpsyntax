<?php declare(strict_types=1);

/**
 * A complete codemod: migrate calls of a deprecated database API to the methods that replaced it,
 * report what could not be migrated, and leave the rest of the file alone.
 *
 * Demonstrates: `find()` + `NameResolver::findGlobalFunction()` + a `Builder` template + `hasInnerComment()`
 *               + `remove(mergeBlankLines: true)`, all together
 * Usage:        php examples/codemod/deprecated-api.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Analyses\{NamespacedSymbols, NameResolver};
use PhpSyntax\Builder;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	namespace App\Legacy;

	class Report
	{
		public function rows(Database $db, int $limit): array
		{
			// the reporting query, do not touch the ORDER BY
			$rows = db_query($db, 'SELECT * FROM orders ORDER BY id DESC');

			$page = db_query(
				$db,
				'SELECT * FROM orders LIMIT ' . $limit,
			);

			$count = db_query($db);
			$named = db_query(query: 'SELECT 1', connection: $db);
			$recent = db_query($db, 'SELECT * FROM orders WHERE id > ?', [$limit]);
			$audit = db_query($db, /* the slow one */ 'SELECT * FROM audit');

			db_close($db);

			return array_filter([$rows, $page, $count, $named, $recent, $audit]);
		}
	}
	PHP);

$parser = new Parser;
$file = $parser->parse($code);

// db_query() is written bare inside App\Legacy, so PHP calls App\Legacy\db_query() if any file of the
// project declares one, which this file cannot tell. The codemod knows its project: it declares no
// function in a namespace, and the complete list given to the resolver says so
$resolver = new NameResolver($file, new NamespacedSymbols(complete: true));
$builder = new Builder($parser);

foreach ($file->find(FunctionCallNode::class) as $call) {
	$function = $resolver->findGlobalFunction($call, ['db_query', 'db_close']);
	if ($function === null) {
		continue;

	} elseif ($function === 'db_close') {
		// the connection closes itself now: the statement goes, and the blank lines around it become one
		$call->parent->remove(mergeBlankLines: true);
		continue;
	}

	// the two arguments the method takes, written by position or by name; null where the call does not say
	$connection = $call->arguments->findArgument('connection', 0);
	$query = $call->arguments->findArgument('query', 1);

	// line, not $currentLine: the report is about the file on disk, which earlier rewrites have moved
	$line = $call->getFirstToken()->line;

	if ($connection === null || $query === null || count($call->arguments->items) !== 2) {
		echo "line $line: left alone, the call does not give exactly the two arguments the method takes\n";

	} elseif ($call->hasInnerComment()) {
		// the rewrite joins the call onto one line, which would move the comment or swallow the code after it
		echo "line $line: left alone, there is a comment inside the call\n";

	} else {
		// the shape is fixed, so it is a template; the two expressions still stand in the file, and the
		// builder takes copies without the whitespace of the place they came from
		$call->replaceWith($builder->expression('$connection->query($query)', connection: $connection->value, query: $query->value));
	}
}

echo "\n";
printDiff($code, (string) $file);
