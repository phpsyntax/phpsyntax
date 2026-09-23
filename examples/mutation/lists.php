<?php declare(strict_types=1);

/**
 * Lists: adding and removing items where the separators, the indentation and the trailing
 * comma have to come out right.
 *
 * Demonstrates: `SeparatedNodeList::append()`, `insert()`, `Node::remove()`, `NodeList::insert()`
 * Usage:        php examples/mutation/lists.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\ArgumentNode;
use PhpSyntax\Nodes\ArrayItemNode;
use PhpSyntax\Nodes\Expression\ArrayNode;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Scalar\StringNode;
use PhpSyntax\Nodes\Statement\NamespaceNode;
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	namespace App;

	use App\Money;

	$config = [
		'driver' => 'mysql', // pdo_mysql must be installed
		'host' => 'localhost',
		'charset' => 'utf8',
	];

	$connection = connect('mysql', 'localhost');
	PHP);

$parser = new Parser;
$file = $parser->parse($code);

// an item of a list is a fragment like any other: it comes back detached, with nothing of the
// code it was parsed in on its edges
$item = $parser->parseFragment(ArrayItemNode::class, "'port' => 3306");
$argument = $parser->parseFragment(ArgumentNode::class, "'utf8mb4'");

// 1. an item in a list written on several lines: it takes the indentation of its neighbour and
//    a separator modelled on the ones already there, the comment after the comma before it stays
//    with the item it is about, and the trailing comma stays last
$array = must($file->findFirst(ArrayNode::class));
$array->items->insert(1, $item);

echo "one item inserted into a multi-line array:\n";
printDiff($code, $step = (string) $file);

// 2. one more argument in a call written on one line: ", " because that is what the list uses
$call = must($file->findFirst(FunctionCallNode::class));
$call->arguments->items->append($argument);

// 3. one item away: the separator that came with it goes too, and so does its line
$host = $array->findFirst(
	ArrayItemNode::class,
	fn(ArrayItemNode $item) => $item->key instanceof StringNode && $item->key->value === 'host',
);
must($host)->remove();

echo "\nan argument appended and an item removed:\n";
printDiff($step, (string) $file);
$step = (string) $file;

// 4. a statement into a list of statements. There are no separators here, so the new item ends its
//    line the way its neighbour does; the blank line after the imports stays where it was
$namespace = must($file->findFirst(NamespaceNode::class));
$namespace->statements->insert(1, $parser->parseStatement('use App\Currency;'));

echo "\nan import inserted between two statements:\n";
printDiff($step, (string) $file);

echo "\n--- the result ---\n", (string) $file, "\n";
