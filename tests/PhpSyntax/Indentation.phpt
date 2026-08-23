<?php declare(strict_types=1);

use PhpSyntax\Indentation;
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Scalar\HeredocNode;
use PhpSyntax\Nodes\Statement\FunctionNode;
use PhpSyntax\Parser;
use PhpSyntax\Style;
use PhpSyntax\Token;
use PhpSyntax\Trivia;
use PhpSyntax\TriviaKind;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function parse(string $code): FileNode
{
	return (new Parser)->parse($code);
}


/** The first token of the file with the text. */
function tokenOf(FileNode $file, string $text): Token
{
	foreach ($file->getIndex()->getTokens() as $token) {
		if ($token->text === $text) {
			return $token;
		}
	}

	throw new LogicException("No token '$text' in the code.");
}


/**
 * @param  list<Trivia>  $trivia
 * @return list<array{TriviaKind, string}>
 */
function texts(array $trivia): array
{
	return array_map(fn(Trivia $item) => [$item->kind, $item->text], $trivia);
}


test('opensLine() asks whether the line ending above the token is trivia', function () {
	Assert::false(Indentation::opensLine(tokenOf(parse('<?php $a;'), '$a')));
	Assert::true(Indentation::opensLine(tokenOf(parse("<?php\n\$a;"), '$a')));

	// inline HTML carries the line ending in its own text, so the line it starts is not one a rule may indent
	$file = parse("<?php\nif (\$a):\n\t?>\n\ttext\n\t<?php\nendif;\n");
	$html = tokenOf($file, "\ttext\n\t");
	Assert::true($html->startsLine());
	Assert::false(Indentation::opensLine($html));
	Assert::true(Indentation::opensLine(tokenOf($file, 'endif'))); // the open tag above endif ends its line

	// a line ending inside an interpolation is part of the string
	$brace = tokenOf(parse("<?php\n\$x = \"{\$a\n\t}\";\n"), '}');
	Assert::true($brace->startsLine());
	Assert::false(Indentation::opensLine($brace));
});


test('findTrivia() is the whitespace standing for the indentation, or the first trivia of a line without one', function () {
	$trivia = Indentation::findTrivia(tokenOf(parse("<?php\n\t// note\n\t\$a;\n"), '$a'));
	Assert::type(Trivia::class, $trivia);
	Assert::same(TriviaKind::Whitespace, $trivia->kind);
	Assert::same("\t", $trivia->text);

	$trivia = Indentation::findTrivia(tokenOf(parse("<?php\n// note\n\$a;\n"), '$a'));
	Assert::type(Trivia::class, $trivia);
	Assert::same(TriviaKind::OpenTag, $trivia->kind);
	Assert::same("<?php\n", $trivia->text);

	Assert::null(Indentation::findTrivia(tokenOf(parse('<?php $a;'), ';')));
});


test('has() asks about the line of the token and about the comments above it', function () {
	$token = tokenOf(parse("<?php\nclass A\n{\n\t// note\n\tpublic \$x;\n}\n"), 'public');
	Assert::true(Indentation::has($token, "\t", "\t"));
	Assert::false(Indentation::has($token, "\t", '')); // the comment does not stand where it is asked to
	Assert::false(Indentation::has($token, '', "\t"));

	// without a comment above it only the line of the token counts
	$token = tokenOf(parse("<?php\nclass A\n{\n\tpublic \$x;\n}\n"), 'public');
	Assert::true(Indentation::has($token, "\t", ''));
});


test('set() writes the indentation of the line and of the comments on their own lines above it', function () {
	$file = parse("<?php\nclass A\n{\n// note\n\t\t/**\n\t\t * doc\n\t\t */\n  public \$x;\n}\n");
	Indentation::set(tokenOf($file, 'public'), "\t", "\t\t");
	Assert::same("<?php\nclass A\n{\n\t\t// note\n\t\t/**\n\t\t * doc\n\t\t */\n\tpublic \$x;\n}\n", (string) $file);

	// a comment sharing the line with the token takes the indentation of the line
	$file = parse("<?php\nif (\$a) {\n/* c */ echo 1;\n}\n");
	Indentation::set(tokenOf($file, 'echo'), "\t", "\t\t");
	Assert::same("<?php\nif (\$a) {\n\t/* c */ echo 1;\n}\n", (string) $file);
});


test('what set() writes, has() answers yes to', function () {
	$shapes = [
		"<?php\nif (\$a) {\n\techo 1;\n}\n",
		"<?php\nif (\$a) {\n\techo 1;\n\t// c\n}\n",
		"<?php\nif (\$a) {\n\techo 1;\n/* c */ }\n", // the comment stands on the line of the brace
		"<?php\nif (\$a) {\n\techo 1;\n\t/*\n\t   c\n\t*/\n}\n",
	];
	foreach ($shapes as $code) {
		foreach ([['', ''], ['', "\t"], ["\t\t", "\t"]] as [$indentation, $comment]) {
			$file = parse($code);
			$brace = tokenOf($file, '}');
			Indentation::set($brace, $indentation, $comment);
			Assert::true(Indentation::has($brace, $indentation, $comment), (string) $file);
		}
	}
});


test('reindentComments() returns null when the comments already stand where they are asked to', function () {
	$leading = tokenOf(parse("<?php\nclass A\n{\n// note\n\t/* b */ public \$x;\n}\n"), 'public')->leadingTrivia;
	Assert::same([
		[TriviaKind::Comment, '// note'],
		[TriviaKind::EndOfLine, "\n"],
		[TriviaKind::Whitespace, "\t"],
		[TriviaKind::Comment, '/* b */'],
		[TriviaKind::Whitespace, ' '],
	], texts($leading));

	$result = Indentation::reindentComments($leading, "\t");
	Assert::type('array', $result);
	Assert::same([
		[TriviaKind::Whitespace, "\t"],
		[TriviaKind::Comment, '// note'],
		[TriviaKind::EndOfLine, "\n"],
		[TriviaKind::Whitespace, "\t"],
		[TriviaKind::Comment, '/* b */'],
		[TriviaKind::Whitespace, ' '],
	], texts($result));
	Assert::null(Indentation::reindentComments($result, "\t"));

	// the whitespace of a line without a comment is left alone
	Assert::null(Indentation::reindentComments([new Trivia(TriviaKind::Whitespace, '  ')], "\t"));
});


test('reindentComment() reshapes a comment whose lines start with a star and moves any other', function () {
	$doc = new Trivia(TriviaKind::DocComment, "/**\n\t * a\n\t */");
	Assert::same("/**\n\t\t * a\n\t\t */", Indentation::reindentComment($doc, "\t", "\t\t")->text);
	Assert::same("/**\n * a\n */", Indentation::reindentComment($doc, "\t", '')->text);
	// the stars go one space in from the opening wherever they stood
	$crooked = new Trivia(TriviaKind::DocComment, "/**\n*a\n*/");
	Assert::same("/**\n\t *a\n\t */", Indentation::reindentComment($crooked, '', "\t")->text);

	$block = new Trivia(TriviaKind::Comment, "/*\n\ta\n\t*/");
	Assert::same("/*\n\t\ta\n\t\t*/", Indentation::reindentComment($block, "\t", "\t\t")->text);
	Assert::same($block, Indentation::reindentComment($block, "\t", "\t"));

	$line = new Trivia(TriviaKind::Comment, '// a');
	Assert::same($line, Indentation::reindentComment($line, '', "\t"));
});


test('width() and advance() count a tab to the next stop of the style', function () {
	$style = new Style;
	Assert::same(8, Indentation::width("\t\t", $style));
	Assert::same(4, Indentation::width('    ', $style));
	Assert::same(4, Indentation::width("  \t", $style));
	Assert::same(0, Indentation::width('', $style));
	Assert::same(6, Indentation::width("\t\t", new Style(tabWidth: 3)));

	Assert::same(4, Indentation::advance(2, "\t", $style));
	Assert::same(9, Indentation::advance(4, "\ta", $style));
	Assert::same(4, Indentation::advance(0, 'ěščř', $style)); // characters, not bytes
});


test('normalize() writes the indentation in the characters of the style', function () {
	$style = new Style;
	Assert::same("\t\t", Indentation::normalize('        ', $style));
	Assert::same("  \t ", Indentation::normalize("  \t ", $style)); // nothing a whole tab stands for

	$spaces = (new Style)->withIndent('    ');
	Assert::same('        ', Indentation::normalize("\t\t", $spaces));
	Assert::same('     ', Indentation::normalize("\t ", $spaces));
});


test('shifting a construct moves every line it opens, the body of a heredoc with them', function () {
	$style = new Style;
	$code = "<?php\nclass A\n{\n\tpublic function m()\n\t{\n\t\t// a note\n\t\t\$x = <<<TXT\n\t\t\tone\n\t\t\t  two\n\t\t\tTXT;\n\t\tif (\$x) {\n\t\t\techo \$x;\n\t\t}\n\t}\n}\n";
	$file = parse($code);
	$method = $file->find(MethodNode::class)[0];
	$heredoc = $file->findFirst(HeredocNode::class);
	Assert::type(HeredocNode::class, $heredoc);
	$value = $heredoc->value;

	Indentation::shift($method, -1, $style);
	Assert::same("<?php\nclass A\n{\npublic function m()\n{\n\t// a note\n\t\$x = <<<TXT\n\t\tone\n\t\t  two\n\t\tTXT;\n\tif (\$x) {\n\t\techo \$x;\n\t}\n}\n}\n", (string) $file);
	Assert::same($value, $heredoc->value); // what PHP takes off the body moved with the body

	Indentation::shift($method, 1, $style);
	Assert::same($code, (string) $file);
	Assert::same($value, $heredoc->value);

	// nothing to take off leaves the line where it is
	$file = parse("<?php\n\$a = 1;\n");
	Indentation::shift($file->statements->getItems()[0], -2, $style);
	Assert::same("<?php\n\$a = 1;\n", (string) $file);

	// and a shift by no level touches nothing
	Indentation::shift($file->statements->getItems()[0], 0, $style);
	Assert::same("<?php\n\$a = 1;\n", (string) $file);
});


test('shifting works in the characters of the style and leaves a heredoc it cannot move', function () {
	$style = (new Style)->withIndent('    ');
	$file = parse("<?php\nclass A\n{\n    public function m()\n    {\n    }\n}\n");
	Indentation::shift($file->find(MethodNode::class)[0], 1, $style);
	Assert::same("<?php\nclass A\n{\n        public function m()\n        {\n        }\n}\n", (string) $file);

	// a body line opening with an interpolation has no indentation of its own to move
	$file = parse("<?php\nfunction f()\n{\n\t\$x = <<<TXT\n\$a b\nTXT;\n}\n");
	$function = $file->findFirst(FunctionNode::class);
	Assert::type(FunctionNode::class, $function);
	Indentation::shift($function, 1, new Style);
	Assert::same("<?php\n\tfunction f()\n\t{\n\t\t\$x = <<<TXT\n\$a b\nTXT;\n\t}\n", (string) $file);
});
