<?php declare(strict_types=1);

use PhpSyntax\{Lexer, Trivia};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('trivia carry the line and the offset they start at in the original file', function () {
	$code = "<?php\n\n  \$a; // c  \n\t\n/* x\n y */ \$b;\n";
	$tokens = (new Lexer)->tokenize($code);
	$lines = [];
	foreach ($tokens as $token) {
		foreach ([...$token->leadingTrivia, ...$token->trailingTrivia] as $trivia) {
			$lines[] = Dumper::findKindName($trivia) . ':' . json_encode($trivia->text, JSON_UNESCAPED_SLASHES) . "@$trivia->line:$trivia->pos";
			Assert::same($trivia->text, substr($code, $trivia->pos, strlen($trivia->text)));
		}
	}

	Assert::same([
		'OpenTag:"<?php\n"@1:0',
		'LineEnding:"\n"@2:6',
		'Whitespace:"  "@3:7',
		'Whitespace:" "@3:12',
		'Comment:"// c  "@3:13',
		'LineEnding:"\n"@3:19',
		'Whitespace:"\t"@4:20',
		'LineEnding:"\n"@4:21',
		'Comment:"/* x\n y */"@5:22',
		'Whitespace:" "@6:32',
		'LineEnding:"\n"@6:36',
	], $lines);
});


test('a trivia made otherwise and one of a fragment have no position', function () {
	$trivia = new Trivia(Trivia::Whitespace, ' ');
	Assert::same([-1, -1], [$trivia->line, $trivia->pos]);
	$tokens = (new Lexer)->tokenize("<?php \$a;\n", withPositions: false);
	$fragment = $tokens[0]->leadingTrivia[0];
	Assert::same([-1, -1], [$fragment->line, $fragment->pos]);
	Assert::same([[-1, -1], [-1, -1], [-1, -1]], array_map(fn($token) => [$token->line, $token->pos], $tokens)); // the end of the file too
});
