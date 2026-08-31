<?php declare(strict_types=1);

/**
 * Lists: adding and removing items where the separators, the indentation and the trailing
 * comma have to come out right, and a trailing comma added where a list lacks one.
 *
 * Demonstrates: `SeparatedNodeList::append()`, `insert()`, `Node::remove()`, `PlainNodeList::insert()`,
 *               `getTrailingSeparator()`, `setTrailingSeparator()`, `Token::fromText()`
 * Usage:        php examples/mutation/lists.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\{ArgumentNode, ArrayItemNode};
use PhpSyntax\Nodes\Expression\{ArrayNode, FunctionCallNode, NewNode};
use PhpSyntax\Nodes\Scalar\StringNode;
use PhpSyntax\Nodes\Statement\NamespaceNode;
use PhpSyntax\Builder;
use PhpSyntax\{Parser, Token};

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

	$pool = new Pool(
		$connection,
		size: 10 // enough for the workers
	);
	PHP);

$parser = new Parser;
$builder = new Builder;
$file = $parser->parse($code);

// an item of a list is a fragment like any other: it comes back detached, with nothing of the
// code it was parsed in on its edges
$item = $builder->fragment(ArrayItemNode::class, "'port' => 3306");
$argument = $builder->fragment(ArgumentNode::class, "'utf8mb4'");

// 1. an item in a list written on several lines: it takes the indentation of its neighbor and
//    a separator modelled on the ones already there, the comment after the comma before it stays
//    with the item it is about, and the trailing comma stays last
$array = $file->findFirst(ArrayNode::class);
$array->items->insert(1, $item);

echo "one item inserted into a multi-line array:\n";
printDiff($code, $step = (string) $file);

// 2. one more argument in a call written on one line: ", " because that is what the list uses
$call = $file->findFirst(FunctionCallNode::class);
$call->arguments->items->append($argument);

// 3. one item away: the separator that came with it goes too, and so does its line
$host = $array->findFirst(
	ArrayItemNode::class,
	fn(ArrayItemNode $item) => $item->key instanceof StringNode && $item->key->toValue() === 'host',
);
$host->remove();

echo "\nan argument appended and an item removed:\n";
printDiff($step, (string) $file);
$step = (string) $file;

// 4. a statement into a list of statements. There are no separators here, so the new item ends its
//    line the way its neighbor does; the blank line after the imports stays where it was
$namespace = $file->findFirst(NamespaceNode::class);
$namespace->statements->insert(1, $builder->statement('use App\Currency;'));

echo "\nan import inserted between two statements:\n";
printDiff($step, $step = (string) $file);

// 5. a list written over several lines ends with a comma, so a rule adds the one that is missing;
//    it stands where the last item ended, which is before the comment
$arguments = $file->findFirst(NewNode::class)->arguments->items;
if ($arguments->getTrailingSeparator() === null) {
	$arguments->setTrailingSeparator(Token::fromText(','));
}

echo "\na trailing comma added:\n";
printDiff($step, (string) $file);

echo "\n--- the result ---\n", (string) $file, "\n";
