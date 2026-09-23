<?php declare(strict_types=1);

/**
 * From a position to a node and back: the way a report of another tool (an editor, a second parser,
 * a static analyser) is brought to the tree.
 *
 * Demonstrates: `FileNode::getIndex()`, `TokenIndex::findNode()`, `getOffsetRange()`, `contains()`
 * Usage:        php examples/positions/offsets.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\Expression\MethodCallNode;
use PhpSyntax\Nodes\IdentifierNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	function orders(Database $db, string $sql): array
	{
		return $db->query($sql)->fetchAll();
	}
	PHP);

$file = new Parser()->parse($code);
$index = $file->getIndex();

// another tool reports a problem by byte offsets, the end exclusive; here they come from the text
$start = (int) strpos($code, '$db->query($sql)');
$end = $start + strlen('$db->query($sql)');
echo "reported: bytes $start to $end\n";

// the outermost node of the class that stands exactly there
$call = must($index->findNode($start, $end, MethodCallNode::class));
echo 'found:    ', $call->text, "\n";

// any class will do, and the answer is the outermost node written exactly at those offsets
echo 'as Node:  ', shortClass(must($index->findNode($start, $end))), "\n";

// a range that does not cover a whole node finds nothing: the tool and the tree disagree
echo 'shifted:  ', var_export($index->findNode($start, $end - 1), return: true), "\n";

// and the way back, for a report of your own that another tool will read
[$from, $to] = $index->getOffsetRange(must($call->findFirst(VariableNode::class))) ?? throw new LogicException;
echo "\$db stands at bytes $from to $to\n";

// the offsets are those of the current text, so after an edit they follow it
assert($call->name instanceof IdentifierNode); // not an expression, as in $db->{$name}()
$call->name->text = 'select';
echo 'the method renamed to select(), the call ends at byte ', ($index->getOffsetRange($call) ?? throw new LogicException)[1], "\n";

// whether a token stands in this file at all, before its offsets mean anything
$fragment = new Parser()->parseExpression('$db');
echo 'a token of a fragment is in this file: ', var_export($index->contains(must($fragment->getFirstToken())), return: true), "\n";
