<?php declare(strict_types=1);

/**
 * Parsing a piece of code rather than a file: an expression, a statement, a type, a name.
 * This is how new nodes are made when you have their text; there are no builder classes.
 *
 * Demonstrates: Parser::parseExpression(), parseStatement(), parseType(), parseName(), parseFragment()
 * Usage:        php examples/parsing/fragments.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\Expression\AssignmentNode;
use PhpSyntax\Nodes\MemberNode;
use PhpSyntax\Nodes\ParameterNode;
use PhpSyntax\ParseException;
use PhpSyntax\Parser;

$parser = new Parser;

$expr = $parser->parseExpression('$price * (1 + $vat)');
echo $expr::class, ': ', $expr, "\n";

$stmt = $parser->parseStatement('if ($qty > 10) { $price *= 0.9; }');
echo $stmt::class, ': ', $stmt, "\n";

$type = $parser->parseType('int|string|null');
echo $type::class, ': ', $type, "\n";

$name = $parser->parseName('\Shop\Cart');
echo $name::class, ': ', $name, "\n";

// the four are shortcuts; parseFragment() parses a node of any kind the grammar writes into a list,
// which is what an insertion into a class body or a signature needs
$method = $parser->parseFragment(MemberNode::class, 'public function total(): float {}');
echo $method::class, ': ', $method, "\n";

$parameter = $parser->parseFragment(ParameterNode::class, "private string \$currency = 'EUR'");
echo $parameter::class, ': ', $parameter, "\n";

// a fragment is a detached node: no parent, no file, no original positions, and no whitespace
// on its edges, so printing it gives the expression alone, ready to be put somewhere
echo 'parent: ', var_export($expr->parent, return: true), "\n";
echo 'printed: "', $parser->parseStatement('  return 1;  '), "\"\n\n";

// and this is what "ready to be put somewhere" means: the fragment lands in a slot and the
// whitespace of the line it lands on is not its business
$code = sample(<<<'PHP'
	<?php
	$total = $price   *   $qty;   // the alignment here is deliberate
	PHP);

$file = $parser->parse($code);
must($file->findFirst(AssignmentNode::class))->expression = $expr;
printDiff($code, (string) $file);

// a fragment must be exactly one thing
try {
	$parser->parseExpression('$a; $b');
} catch (ParseException $e) {
	echo 'ParseException: ', $e->getMessage(), "\n";
}
