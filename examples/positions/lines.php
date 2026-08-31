<?php declare(strict_types=1);

/**
 * Lines and columns that follow your edits, and the original position that never moves.
 *
 * Demonstrates: `Token::getCurrentLine()`, `getCurrentColumn()`, `getCurrentOffset()`, `$line`, `Node::getStartLine()`
 * Usage:        php examples/positions/lines.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\Statement\{ClassNode, ReturnNode};
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	class Cart
	{
		public function total(): float
		{
			return $this->sum;
		}
	}
	PHP);

$file = new Parser()->parse($code);
$return = $file->findFirst(ReturnNode::class);
$token = $return->getFirstToken();

echo 'return is on line ', $token->getCurrentLine(), ', column ', $token->getCurrentColumn(), ', offset ', $token->getCurrentOffset(), "\n";

// insert two blank lines above the class
$file->findFirst(ClassNode::class)->getFirstToken()->setBlankLinesBefore(2, "\n");

// the index followed the change: the line is new, the original one is not
echo 'after the edit: line ', $token->getCurrentLine(), ', originally ', $token->line, "\n";
echo 'the node agrees: ', $return->getStartLine(), "\n";

// a report says where the code is now and where it was when it was read
echo "\n", 'Cart::total() returns on line ', $return->getStartLine(), " of the rewritten file\n";
echo 'which was line ', $token->line, " of the file on disk\n";
