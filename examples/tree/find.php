<?php declare(strict_types=1);

/**
 * Finding things in the tree: by node class, by a predicate, and upwards to an ancestor.
 *
 * Demonstrates: Node::find(), Node::findFirst(), Node::findAncestor()
 * Usage:        php examples/tree/find.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\Scalar\StringNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	class Invoice
	{
		public function render(array $rows): string
		{
			$html = sprintf('<table id="%s">', $this->id);
			foreach ($rows as $row) {
				$html .= '<tr><td>' . htmlspecialchars($row['label']) . '</td></tr>';
			}
			return $html . '</table>';
		}
	}
	PHP);

$file = new Parser()->parse($code);

// every node of a class, as a snapshot safe to iterate while mutating the tree
foreach ($file->find(StringNode::class) as $string) {
	echo 'string on line ', $string->getStartLine(), ': ', $string->value, "\n";
}

echo "\n";

// a class filter keeps the type, so the node's own slots are right there
foreach ($file->find(FunctionCallNode::class) as $call) {
	if ($call->name instanceof NameNode) {
		echo 'call: ', $call->name->text, '() with ', count($call->arguments->items), " argument(s)\n";
	}
}

echo "\n";

// a predicate where the class alone is not enough; it gets the node typed, so its slots are at hand
$long = $file->find(StringNode::class, fn(StringNode $string) => strlen($string->value) > 8);
echo count($long), " string literals are longer than 8 characters\n\n";

// findFirst() stops at the first hit, findAncestor() walks up from a node
$call = $file->findFirst(
	FunctionCallNode::class,
	fn(FunctionCallNode $call) => $call->name instanceof NameNode && $call->name->equals('htmlspecialchars'),
);
$method = $call?->findAncestor(MethodNode::class);
$class = $call?->findAncestor(ClassNode::class);
echo 'htmlspecialchars() is called in ', $class?->name->text, '::', $method?->name->text, "()\n";
