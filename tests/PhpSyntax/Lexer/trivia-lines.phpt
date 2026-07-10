<?php declare(strict_types=1);

use PhpSyntax\Lexer\Lexer;
use PhpSyntax\Trivia;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


test('trivia carry the line they start on in the original file', function () {
	$tokens = (new Lexer)->tokenize("<?php\n\n  \$a; // c  \n\t\n/* x\n y */ \$b;\n");
	$lines = [];
	foreach ($tokens as $token) {
		foreach ([...$token->leadingTrivia, ...$token->trailingTrivia] as $trivia) {
			$lines[] = $trivia->kind->name . ':' . json_encode($trivia->text, JSON_UNESCAPED_SLASHES) . '@' . $trivia->originalLine;
		}
	}

	Assert::same([
		'OpenTag:"<?php\n"@1',
		'EndOfLine:"\n"@2',
		'Whitespace:"  "@3',
		'Whitespace:" "@3',
		'Comment:"// c  "@3',
		'EndOfLine:"\n"@3',
		'Whitespace:"\t"@4',
		'EndOfLine:"\n"@4',
		'Comment:"/* x\n y */"@5',
		'Whitespace:" "@6',
		'EndOfLine:"\n"@6',
	], $lines);
	Assert::null(new Trivia(PhpSyntax\TriviaKind::Whitespace, ' ')->originalLine);
	Assert::null((new Lexer)->tokenize("<?php \$a;\n", withPositions: false)[0]->leadingTrivia[0]->originalLine);
});
