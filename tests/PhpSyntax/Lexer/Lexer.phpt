<?php declare(strict_types=1);

use PhpSyntax\Lexer\Lexer;
use PhpSyntax\ParseException;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;
use PhpSyntax\Trivia;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


/**
 * One line per token: kind, text, [leading trivia], [trailing trivia]; trivia as Kind:text, Kind*:text inside interpolation.
 * @param  list<Token>  $tokens
 * @return list<string>
 */
function dump(array $tokens): array
{
	static $names;
	$names ??= array_flip(array_filter(new ReflectionClass(TokenKind::class)->getConstants(), 'is_int'));
	$escape = fn(string $s) => strtr($s, ["\n" => '\n', "\r" => '\r', "\t" => '\t']);
	$trivia = fn(Trivia $t) => $t->kind->name . ($t->inInterpolation ? '*' : '') . ':' . $escape($t->text);
	$lines = [];
	foreach ($tokens as $token) {
		$lines[] = sprintf(
			'%s %s [%s] [%s]',
			$names[$token->kind] ?? "'$token->text'",
			$escape($token->text),
			implode(', ', array_map($trivia, $token->leadingTrivia)),
			implode(', ', array_map($trivia, $token->trailingTrivia)),
		);
	}

	return $lines;
}


/** @return list<Token> */
function tokenize(string $code): array
{
	$tokens = (new Lexer)->tokenize($code);
	Assert::same($code, implode('', $tokens), 'round-trip');
	return $tokens;
}


test('trailing trivia reaches the end of line, the rest is leading', function () {
	Assert::same([
		'Identifier foo [OpenTag:<?php\n] []',
		"'(' ( [] []",
		"')' ) [] []",
		"';' ; [] [Whitespace: , Comment:// call, EndOfLine:\\n]",
		'Identifier bar [EndOfLine:\n, Comment:// before, EndOfLine:\n, Whitespace:\t] []',
		"'(' ( [] []",
		"')' ) [] []",
		"';' ; [] [EndOfLine:\\n]",
		'EndOfFile  [EndOfLine:\n] []',
	], dump(tokenize("<?php\nfoo(); // call\n\n// before\n\tbar();\n\n")));
});


test('original positions', function () {
	$tokens = tokenize("<?php\n\n\$a = 1;");
	Assert::same([TokenKind::Variable, 7, 3], [$tokens[0]->kind, $tokens[0]->originalOffset, $tokens[0]->originalLine]);
	Assert::same([TokenKind::EndOfFile, 14, 3], [$tokens[4]->kind, $tokens[4]->originalOffset, $tokens[4]->originalLine]);
});


test('line endings: LF, CRLF and a lone CR', function () {
	Assert::same([
		"';' ; [OpenTag:<?php ] [EndOfLine:\\n]",
		"';' ; [] [EndOfLine:\\r\\n]",
		"';' ; [] [EndOfLine:\\r]",
		"';' ; [] [EndOfLine:\\r\\n]",
		'EndOfFile  [] []',
	], dump(tokenize("<?php ;\n;\r\n;\r;\r\n")));
	Assert::same(5, tokenize("<?php ;\n;\r\n;\r;\r\n")[4]->originalLine);
});


test('empty input and input without PHP', function () {
	Assert::same(['EndOfFile  [] []'], dump(tokenize('')));
	Assert::same(1, tokenize('')[0]->originalLine);
	Assert::same([
		'InlineHtml <b>x</b>\n\n [] []',
		'EndOfFile  [] []',
	], dump(tokenize("<b>x</b>\n\n")));
});


test('BOM and hashbang are inline HTML', function () {
	Assert::same([
		"InlineHtml \u{FEFF} [] []",
		'Variable $a [OpenTag:<?php ] []',
		"';' ; [] []",
		'EndOfFile  [] []',
	], dump(tokenize("\u{FEFF}<?php \$a;")));
	Assert::same([
		'InlineHtml #!/usr/bin/env php\n [] []',
		'Variable $a [OpenTag:<?php ] []',
		"';' ; [] []",
		'EndOfFile  [] []',
	], dump(tokenize("#!/usr/bin/env php\n<?php \$a;")));
});


test('open tag keeps its whitespace, even at the end of file', function () {
	Assert::same([
		'EndOfFile  [OpenTag:<?php] []',
	], dump(tokenize('<?php')));
	Assert::same([
		'Variable $a [OpenTag:<?php\r\n, EndOfLine:\r\n, Whitespace:  ] []',
		'EndOfFile  [] []',
	], dump(tokenize("<?php\r\n\r\n  \$a")));
});


test('close tag keeps its newline and never has trailing trivia', function () {
	Assert::same([
		'Variable $a [OpenTag:<?php ] []',
		"';' ; [] [Whitespace: ]",
		'CloseTag ?>\n [] []',
		'InlineHtml \n<p>\n [] []',
		'Variable $b [OpenTag:<?php ] []',
		"';' ; [] []",
		'CloseTag ?> [] []',
		'EndOfFile  [] []',
	], dump(tokenize("<?php \$a; ?>\n\n<p>\n<?php \$b;?>")));
});


test('single-line comment ends before the close tag', function () {
	Assert::same([
		'CloseTag ?>\n [OpenTag:<?php , Comment:// foo ] []',
		'InlineHtml x [] []',
		'EndOfFile  [] []',
	], dump(tokenize("<?php // foo ?>\nx")));
});


test('open tag with echo', function () {
	Assert::same([
		'OpenTagWithEcho <?= [] [Whitespace: ]',
		'Variable $a [] [Whitespace: ]',
		'CloseTag ?>\n [] []',
		'EndOfFile  [] []',
	], dump(tokenize("<?= \$a ?>\n")));
});


test('__halt_compiler data', function () {
	Assert::same([
		'HaltCompiler __halt_compiler [OpenTag:<?php ] [Whitespace: ]',
		"'(' ( [] [Whitespace: ]",
		"')' ) [] []",
		"';' ; [] []",
		'HaltCompilerData  data\n<?php nope [] []',
		'EndOfFile  [] []',
	], dump(tokenize("<?php __halt_compiler ( ); data\n<?php nope")));
	Assert::same([
		'HaltCompiler __halt_compiler [OpenTag:<?php ] []',
		"'(' ( [] []",
		"')' ) [] [Whitespace: ]",
		'CloseTag ?>\n [] []',
		'HaltCompilerData data [] []',
		'EndOfFile  [] []',
	], dump(tokenize("<?php __halt_compiler() ?>\ndata")));
	Assert::same([
		'HaltCompiler __halt_compiler [OpenTag:<?php ] []',
		"'(' ( [] []",
		"')' ) [] []",
		"';' ; [] []",
		'EndOfFile  [] []',
	], dump(tokenize('<?php __halt_compiler();')));
});


test('heredoc and nowdoc keep whitespace in token text', function () {
	Assert::same([
		'Variable $a [OpenTag:<?php ] [Whitespace: ]',
		"'=' = [] [Whitespace: ]",
		'StartHeredoc <<<EOT\n [] []',
		'EncapsedAndWhitespace \tfoo  [] []',
		'Variable $b [] []',
		'EncapsedAndWhitespace \n\tbar\n [] []',
		'EndHeredoc \tEOT [] []',
		"';' ; [] [EndOfLine:\\n]",
		'Variable $c [] [Whitespace: ]',
		"'=' = [] [Whitespace: ]",
		"StartHeredoc <<<'EOT'\\n [] []",
		'EndHeredoc EOT [] []',
		"';' ; [] []",
		'EndOfFile  [] []',
	], dump(tokenize("<?php \$a = <<<EOT\n\tfoo \$b\n\tbar\n\tEOT;\n\$c = <<<'EOT'\nEOT;")));
});


test('trivia inside string interpolation is flagged', function () {
	Assert::same([
		'\'"\' " [OpenTag:<?php ] []',
		'CurlyOpen { [] []',
		'Variable $a [] [Whitespace*: , Comment*:/* c */, Whitespace*: ]',
		"'}' } [] []",
		'EncapsedAndWhitespace x [] []',
		'DollarOpenCurlyBraces ${ [] []',
		'StringVariableName b [] []',
		"'}' } [] []",
		'\'"\' " [] []',
		"';' ; [] [Whitespace: ]",
		'\'"\' " [] []',
		'CurlyOpen { [] []',
		'Variable $a [] []',
		"'[' [ [] []",
		'Identifier f [] []',
		"'(' ( [] []",
		"'{' { [] [EndOfLine*:\\n]",
		"'}' } [] []",
		"')' ) [] []",
		"']' ] [] [Whitespace*: ]",
		"'}' } [] []",
		'\'"\' " [] []',
		"';' ; [] [Whitespace: ]",
		'Variable $x [] []',
		"';' ; [] []",
		'EndOfFile  [] []',
	], dump(tokenize("<?php \"{\$a /* c */ }x\${b}\"; \"{\$a[f({\n})] }\"; \$x;")));
});


test('cast with spaces is one token', function () {
	Assert::same([
		'IntCast ( int ) [OpenTag:<?php ] [Whitespace: ]',
		'Variable $a [] []',
		"';' ; [] []",
		'EndOfFile  [] []',
	], dump(tokenize('<?php ( int ) $a;')));
});


test('comments at the end of file', function () {
	Assert::same([
		'EndOfFile  [OpenTag:<?php , Comment:// x] []',
	], dump(tokenize('<?php // x')));
	Assert::same([
		"';' ; [OpenTag:<?php ] [Whitespace: , Comment:# x, EndOfLine:\\n]",
		'EndOfFile  [DocComment:/** d */] []',
	], dump(tokenize("<?php ; # x\n/** d */")));
	Assert::same([
		"';' ; [OpenTag:<?php ] [Whitespace: , Comment:/* a\\n b */, Whitespace: , Comment:// c, EndOfLine:\\n]",
		'EndOfFile  [] []',
	], dump(tokenize("<?php ; /* a\n b */ // c\n")));
});


test('lexer errors', function () {
	$e = Assert::exception(fn() => tokenize("<?php\n\$a = \x01;"), ParseException::class, 'Unexpected character "%a%" (ASCII 1)');
	Assert::type(ParseException::class, $e);
	Assert::same([2, 6, 11], [$e->sourceLine, $e->sourceColumn, $e->sourceOffset]);
	$e = Assert::exception(fn() => tokenize("<?php\n/* foo"), ParseException::class, 'Unterminated comment');
	Assert::type(ParseException::class, $e);
	Assert::same([2, 1, 6], [$e->sourceLine, $e->sourceColumn, $e->sourceOffset]);
});
