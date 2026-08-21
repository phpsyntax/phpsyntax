<?php declare(strict_types=1);

/**
 * Parse a file into a tree, print the tree, get the file back byte for byte.
 *
 * Demonstrates: Parser::parse(), Printer::print(), the round-trip invariant
 * Usage:        php examples/parsing/round-trip.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\Scalar\FloatNode;
use PhpSyntax\Parser;
use PhpSyntax\Printer;

// deliberately untidy: mixed indentation, comments in odd places, a heredoc, a close tag with HTML after it
$code = sample(<<<'PHP'
	<?php
	namespace   Shop ;   // two spaces, because someone's editor
	  /* block */ function total( array $items,float $vat=0.21){
			$sum = 0 ;
		foreach($items as $item)   $sum+=$item['price']*$item['qty'];
		return <<<TXT
		  Total: {$sum}
		TXT;
	}
	?>
	<p>Thanks for shopping.</p>
	PHP);

$parser = new Parser;
$file = $parser->parse($code);

echo Printer::print($file) === $code ? "identical\n" : "DIFFERENT\n";

// (string) is the same thing: every node and token prints itself
echo (string) $file === $code ? "identical again\n" : "DIFFERENT\n";

// now change one literal, the VAT rate, and print what a reviewer would see
must($file->findFirst(FloatNode::class))->token->setText('0.23');
printDiff($code, (string) $file);

// the invariant holds over a whole code base; here the sources of PhpSyntax itself
$files = $failed = 0;
$directory = new RecursiveDirectoryIterator(__DIR__ . '/../../src', FilesystemIterator::SKIP_DOTS);
foreach (new RecursiveIteratorIterator($directory) as $path) {
	if ($path->getExtension() === 'php') {
		$source = (string) file_get_contents((string) $path);
		$files++;
		if ((string) $parser->parse($source) !== $source) {
			echo "round trip failed: $path\n";
			$failed++;
		}
	}
}

echo "\n", $failed === 0
	? "$files files parsed and printed back unchanged\n"
	: "$failed of $files files came back different\n";
exit($failed === 0 ? 0 : 1);
