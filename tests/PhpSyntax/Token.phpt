<?php declare(strict_types=1);

use PhpSyntax\{Parser, Token, Trivia};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('a token is made from its text by the lexer', function () {
	$cases = [
		';' => ord(';'), '$a' => Token::Variable, 'as' => Token::As, '?->' => Token::NullsafeObjectOperator,
		"'a b'" => Token::ConstantEncapsedString, '(int)' => Token::IntCast, '0x1F' => Token::Integer,
	];
	foreach ($cases as $text => $kind) {
		$token = Token::fromText($text);
		Assert::same($kind, $token->id, $text);
		Assert::same($text, $token->text);
		Assert::same(-1, $token->line);
	}

	foreach (['', 'a b', '$a;', ' ;', '// c', "'open", '<?php'] as $text) {
		Assert::exception(fn() => Token::fromText($text), InvalidArgumentException::class, PhpSyntax\Helpers::formatCode($text) . ' is not a single token.');
	}
});


test('a trivia is made from its text', function () {
	$cases = [
		' ' => Trivia::Whitespace, "\t  " => Trivia::Whitespace, "\n" => Trivia::LineEnding, "\r\n" => Trivia::LineEnding, "\r" => Trivia::LineEnding,
		'// c' => Trivia::Comment, '# c' => Trivia::Comment, '/* c */' => Trivia::Comment, '/**/' => Trivia::Comment, "/* a\n b */" => Trivia::Comment,
		'/** d */' => Trivia::DocComment, "<?php\n" => Trivia::OpenTag, '<?php ' => Trivia::OpenTag, "<?php\r\n" => Trivia::OpenTag, '<?' => Trivia::OpenTag,
		'<?PHP ' => Trivia::OpenTag, "<?Php\n" => Trivia::OpenTag,
	];
	foreach ($cases as $text => $kind) {
		$trivia = Trivia::fromText((string) $text);
		Assert::same($kind, $trivia->id, (string) $text);
		Assert::same((string) $text, $trivia->text);
	}

	foreach (['', "\n\n", " \n", '#[A]', "// c\n", '/* open', '/* a */ b', '// a ?> b', '<?php', '<?=', 'x'] as $text) {
		Assert::exception(fn() => Trivia::fromText($text), InvalidArgumentException::class, PhpSyntax\Helpers::formatCode($text) . ' is not whitespace, a line ending, a comment or an open tag.');
	}
});


test('a token finds the ancestor from its parent up', function () {
	$file = (new Parser)->parse('<?php class A { public function m() { $a; } }');
	$token = ($file->findFirst(PhpSyntax\Nodes\Expression\VariableNode::class) ?? throw new LogicException)->getFirstToken();
	Assert::type(PhpSyntax\Nodes\Expression\VariableNode::class, $token->findAncestor(PhpSyntax\Nodes\Expression\VariableNode::class));
	Assert::type(PhpSyntax\Nodes\Member\MethodNode::class, $token->findAncestor(PhpSyntax\Nodes\FunctionLikeNode::class));
	Assert::null($token->findAncestor(PhpSyntax\Nodes\Statement\FunctionNode::class));
	Assert::null(new Token(Token::Variable, '$a')->findAncestor(PhpSyntax\Node::class));
});


test('token from the lexer carries its original position', function () {
	$token = new Token(Token::Variable, '$a', line: 1, pos: 6);
	Assert::same(Token::Variable, $token->id);
	Assert::same('$a', $token->text);
	Assert::same(6, $token->pos);
	Assert::same(1, $token->line);
	Assert::same([], $token->leadingTrivia);
	Assert::same([], $token->trailingTrivia);
});


test('synthetic token has no original position', function () {
	$token = new Token(ord(';'), ';');
	Assert::same(-1, $token->pos);
	Assert::same(-1, $token->line);
});


test('string form is leading trivia, text and trailing trivia', function () {
	$token = new Token(Token::Return, 'return');
	$token->setLeadingTrivia([
		new Trivia(Trivia::LineEnding, "\n"),
		new Trivia(Trivia::Whitespace, "\t"),
	]);
	$token->setTrailingTrivia([
		new Trivia(Trivia::Whitespace, ' '),
		new Trivia(Trivia::Comment, '// done'),
	]);
	Assert::same("\n\treturn // done", (string) $token);
});


test('trivia is a token PHP ignores', function () {
	$trivia = new Trivia(Trivia::Comment, '/* c */');
	Assert::same(Trivia::Comment, $trivia->id);
	Assert::same('/* c */', $trivia->text);
	Assert::same(-1, $trivia->line);
	Assert::same(-1, $trivia->pos);
	Assert::false($trivia->inInterpolation);
	Assert::true($trivia->is(Trivia::Comment));
	Assert::true($trivia->isIgnorable());
	Assert::same('T_COMMENT', $trivia->getTokenName());
	Assert::same('T_WHITESPACE', new Trivia(Trivia::LineEnding, "\n")->getTokenName());
	Assert::true(new Trivia(Trivia::LineEnding, "\n")->isIgnorable());
});


test('a trivia with another text is a copy standing where the original stood', function () {
	$trivia = new Trivia(Trivia::Comment, '// a  ', 3, 10);
	$trivia->inInterpolation = true;
	$trimmed = $trivia->withText('// a');
	Assert::notSame($trivia, $trimmed);
	Assert::same('// a  ', $trivia->text);
	Assert::same(Trivia::Comment, $trimmed->id);
	Assert::same('// a', $trimmed->text);
	Assert::true($trimmed->inInterpolation);
	Assert::same([3, 10], [$trimmed->line, $trimmed->pos]);
});


test('a copy of a token does not keep the file of the original alive', function () {
	$file = (new Parser)->parse('<?php $a; $b;');
	$token = $file->getTokens()[2];
	Assert::same(1, $token->getCurrentLine()); // numbers the tokens
	$copy = clone $token;
	$weak = WeakReference::create($file);
	unset($file, $token);
	gc_collect_cycles();
	Assert::null($weak->get());
	Assert::same('$b', $copy->text);
});
