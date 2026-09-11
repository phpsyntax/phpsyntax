<?php declare(strict_types=1);

/**
 * Lines and columns that follow your edits, and the original position that never moves.
 *
 * Demonstrates: Token::getLine(), getColumn(), getOffset(), $originalLine, Node::getStartLine()
 * Usage:        php examples/positions/lines.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\Statement\ReturnNode;
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
$return = must($file->findFirst(ReturnNode::class));
$token = must($return->getFirstToken());

echo 'return is on line ', $token->getLine(), ', column ', $token->getColumn(), ', offset ', $token->getOffset(), "\n";

// insert two blank lines above the class
must($file->find(ClassNode::class)[0]->getFirstToken())->setBlankLinesBefore(2);

// the index followed the change: the line is new, the original one is not
echo 'after the edit: line ', $token->getLine(), ', originally ', $token->originalLine, "\n";
echo 'the node agrees: ', $return->getStartLine(), "\n";

// a report says where the code is now and where it was when it was read
echo "\n", 'Cart::total() returns on line ', $return->getStartLine(), " of the rewritten file\n";
echo 'which was line ', $token->originalLine, " of the file on disk\n";
