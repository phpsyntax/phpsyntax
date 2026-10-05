<?php declare(strict_types=1);


/**
 * setText() writes another spelling of the same kind and refuses a text the token would be read as another kind for.
 */
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\{Parser, Token};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


/** @return list<Token> */
function lexTokens(string $code): array
{
	return (new Parser)->parse($code)->getTokens();
}


function findByText(string $code, string $text): Token
{
	return array_find(lexTokens($code), fn(Token $token) => $token->text === $text) ?? throw new LogicException;
}


test('another spelling of the same kind is written', function () {
	$cases = [
		['<?php ARRAY();', 'ARRAY', 'array'],
		['<?php $a = (integer) $b;', '(integer)', '(int)'],
		['<?php $a = ( INT ) $b;', '( INT )', '(int)'],
		['<?php $a <> $b;', '<>', '!='],
		['<?php $a = 0x1F;', '0x1F', '31'],
		['<?php $a = 1.5;', '1.5', '1_000.5'],
		['<?php $a = \'x\';', "'x'", '"y"'],
		['<?php $a = TRUE;', 'TRUE', 'true'],
		['<?php $old;', '$old', '$new'],
		['<?php foo();', 'foo', 'bar'],
		['<?php $a->foo;', 'foo', 'list'], // an identifier is any name, a keyword among them
		["<?php \$a = \"x {\$b} y\";", 'x ', 'anything at all'], // the content of a string
		["<?php echo 1 ?>\nhtml", "?>\n", '?>'],
	];
	foreach ($cases as [$code, $old, $new]) {
		$token = findByText($code, $old);
		$token->setText($new);
		Assert::same($new, $token->text, $code);
	}
});


test('a text of another kind is refused, and the token stays as it was', function () {
	$cases = [
		['<?php $a + $b;', '+', '==='],
		['<?php class A { protected function f() {} }', 'protected', 'public'],
		['<?php $a;', '$a', 'a'],
		['<?php $a;', '$a', '$a + 1'],
		['<?php $a = 1;', '1', '1.5'],
		['<?php $a = 1;', '1', '$b'],
		['<?php foo();', 'foo', 'foo bar'],
		['<?php foo();', 'foo', '/* c */ foo'],
		['<?php $a = "x$b";', '"', "'"],
	];
	foreach ($cases as [$code, $old, $new]) {
		$token = findByText($code, $old);
		assertRefused(
			fn() => $token->setText($new),
			InvalidArgumentException::class,
			'Token ' . PhpSyntax\Helpers::formatCode($old) . ' cannot be written as ' . PhpSyntax\Helpers::formatCode($new) . ', which is another kind of token; `replaceWith()` puts one in its place.',
			$token,
		);
	}
});


test('writing an identifier that stands as a keyword gives it the kind of an identifier', function () {
	$file = (new Parser)->parse('<?php class A { function list() {} }');
	$name = ($file->findFirst(MethodNode::class) ?? throw new LogicException)->name;
	Assert::same(Token::List, $name->token->id);
	$name->text = 'items';
	Assert::same(Token::Identifier, $name->token->id);
	Assert::same('<?php class A { function items() {} }', (string) $file);
	$name->text = 'list';
	Assert::same('<?php class A { function list() {} }', (string) $file);
});


test('the lexer reads every token of the corpus from its own text, but those only the code around tells', function () {
	$contentKinds = new ReflectionClassConstant(Token::class, 'ContentKinds')->getValue();
	// the kind of these the neighbors decide, and each has a single spelling, so a write of another one fails anyway
	$fixed = [
		ord('"'), ord('`'), Token::CurlyOpen, Token::DollarOpenCurlyBraces, Token::AmpersandFollowedByVariableOrVariadic,
		Token::OpenTagWithEcho,
	];
	$lexer = new PhpSyntax\Lexer;
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../corpus', FilesystemIterator::SKIP_DOTS));
	$count = 0;
	foreach ($files as $file) {
		if (!preg_match('~\.(php|phpt|inc)$~', $file->getFilename())) {
			continue;
		}

		try {
			$tree = (new Parser)->parse((string) file_get_contents($file->getPathname()));
		} catch (PhpSyntax\ParseException) {
			continue;
		}

		foreach ($tree->getTokens() as $token) {
			if (
				$token->is(Token::EndOfFile)
				|| isset($contentKinds[$token->id])
				|| $token->is($fixed)
				|| $token->is(Token::Identifier) // any name, a keyword taken as one too
				|| $token->is(Token::Enum) // a keyword only before a name
			) {
				continue;
			}

			$read = $lexer->tokenize('<?php ' . $token->text, withPositions: false);
			Assert::same([$token->id, $token->text], [$read[0]->id, $read[0]->text], $file->getFilename() . ': ' . $token->text);
			$count++;
		}
	}

	Assert::true($count > 10000);
});
