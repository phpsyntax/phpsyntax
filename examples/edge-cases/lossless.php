<?php declare(strict_types=1);

/**
 * The files that break naive tools: a BOM, a hashbang, CRLF, inline HTML, a close tag,
 * a heredoc and data after __halt_compiler(). All of them round-trip.
 *
 * Demonstrates: the round-trip invariant on real-world oddities, InlineHtmlNode::isPreamble(),
 *               Style::detectEol(), Token::isSemicolon()
 * Usage:        php examples/edge-cases/lossless.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\Statement\InlineHtmlNode;
use PhpSyntax\Parser;
use PhpSyntax\Style;

$parser = new Parser;

$cases = [
	'byte order mark' => "\xEF\xBB\xBF<?php\n\$a = 1;\n",
	'hashbang line' => "#!/usr/bin/env php\n<?php\n\$a = 1;\n",
	'CRLF line endings' => "<?php\r\nif (\$a) {\r\n\t\$b = 2;\r\n}\r\n",
	'no trailing newline' => '<?php $a = 1;',
	'close tag and HTML' => "<?php \$title = 'Hi';?>\n<h1><?= \$title ?></h1>\n",
	'heredoc with interpolation' => "<?php\n\$s = <<<TXT\n\tvalue: {\$a}\n\tTXT;\n",
	'data after halt' => "<?php\n__halt_compiler();\nthis is not PHP at all\n",
	'trailing whitespace' => "<?php\n\$a = 1;   \n\n\n",
];

foreach ($cases as $label => $code) {
	$file = $parser->parse($code);
	printf("%-28s %s\n", $label, (string) $file === $code ? 'round trip ok' : 'DIFFERENT');
}

// what the tree says about the oddities, when a tool needs to know
echo "\n";
$file = $parser->parse("\xEF\xBB\xBF#!/usr/bin/env php\n<?php\n\$a = 1;\n");
$html = $file->find(InlineHtmlNode::class)[0];
echo 'the text before <?php is only a BOM and a hashbang: ', var_export($html->isPreamble(), return: true), "\n";

$file = $parser->parse("<?php \$title = 'Hi';?>\n<h1></h1>\n");
$closeTag = must(must($file->statements->getItems()[1]->getFirstToken()));
echo 'the close tag stands in for a semicolon: ', var_export($closeTag->isSemicolon(), return: true), "\n";
echo 'and it keeps the newline PHP swallows: ', json_encode($closeTag->text), "\n";

echo 'prevailing line ending of a CRLF file: ', json_encode(Style::detectEol("<?php\r\n\$a = 1;\r\n")), "\n";
