<?php declare(strict_types=1);

use PhpSyntax\{Builder, NameForm, Parser};
use PhpSyntax\Nodes\NameNode;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


function name(string $code): NameNode
{
	return (new Builder)->name($code);
}


test('forms and parts', function () {
	$name = name('Foo');
	Assert::same(NameForm::Unqualified, $name->form);
	Assert::same(['Foo'], $name->parts);
	Assert::same('Foo', $name->text);

	$name = name('Foo\Bar');
	Assert::same(NameForm::Qualified, $name->form);
	Assert::same(['Foo', 'Bar'], $name->parts);

	$name = name('\Foo\Bar');
	Assert::same(NameForm::FullyQualified, $name->form);
	Assert::same(['Foo', 'Bar'], $name->parts);
	Assert::same('\Foo\Bar', $name->text);

	$name = name('namespace\Bar');
	Assert::same(NameForm::Relative, $name->form);
	Assert::same(['Bar'], $name->parts);
});


test('the short name, the qualification and the special class names', function () {
	Assert::same('Bar', name('Foo\Bar')->shortName);
	Assert::same('Foo', name('Foo')->shortName);
	Assert::true(name('self')->isSpecialClass());
	Assert::true(name('STATIC')->isSpecialClass());
	Assert::false(name('\self')->isSpecialClass());
	Assert::false(name('Foo')->isSpecialClass());
});


test('writing a name replaces the token with the one it is written as', function () {
	$name = name('Foo');
	$name->text = 'Bar\Baz';
	Assert::same('Bar\Baz', $name->text);
	Assert::same(NameForm::Qualified, $name->form);
	Assert::same('Bar\Baz', (string) $name);

	$name->text = '\Qux';
	Assert::same(NameForm::FullyQualified, $name->form);
	$name->text = 'static';
	Assert::true($name->isKeyword());
	$name->text = 'Foo';
	Assert::false($name->isKeyword());

	// the name must be the whole token: no whitespace and no comment may reach the text of a token
	Assert::exception(fn() => $name->text = 'a b', InvalidArgumentException::class, '`a b` is not a name.');
	Assert::exception(fn() => $name->text = ' Foo', InvalidArgumentException::class, '` Foo` is not a name.');
	Assert::exception(fn() => $name->text = 'Foo ', InvalidArgumentException::class, '`Foo ` is not a name.');
	Assert::exception(fn() => $name->text = 'Foo /* c */', InvalidArgumentException::class, '`Foo /* c */` is not a name.');
	Assert::exception(fn() => $name->text = '', InvalidArgumentException::class, "`''` is not a name.");

	// nor any other single token: a number, a variable, an operator, a string
	foreach (['123', '$a', '+', '"text"', "'text'", '1.5'] as $text) {
		Assert::exception(fn() => NameNode::fromText($text), InvalidArgumentException::class, PhpSyntax\Helpers::formatCode($text) . ' is not a name.');
	}

	Assert::same(['Foo', 'self', 'list', 'A\B', '\A\B', 'namespace\A'], array_map(fn($text) => NameNode::fromText($text)->text, ['Foo', 'self', 'list', 'A\B', '\A\B', 'namespace\A']));
});


test('a name built from text is written the way the text says', function () {
	Assert::same(NameForm::Unqualified, NameNode::fromText('Foo')->form);
	Assert::same(NameForm::Qualified, NameNode::fromText('Foo\Bar')->form);
	Assert::same(NameForm::FullyQualified, NameNode::fromText('\Foo\Bar')->form);
	Assert::same('\Foo\Bar', (string) NameNode::fromText('\Foo\Bar'));
	Assert::true(NameNode::fromText('static')->isKeyword());
	Assert::exception(fn() => NameNode::fromText('a b'), InvalidArgumentException::class, '`a b` is not a name.');
});


test('writing a name keeps the trivia around it', function () {
	$file = (new Parser)->parse("<?php\nnew /* c */ Foo();\n");
	$name = $file->find(NameNode::class)[0];
	$name->text = 'Bar';
	Assert::same("<?php\nnew /* c */ Bar();\n", (string) $file);
});


test('keywords accepted as names', function () {
	Assert::true(name('static')->isKeyword());
	Assert::false(name('Foo')->isKeyword());
	Assert::false(name('\Foo')->isKeyword());
	$file = (new Parser)->parse('<?php function f(array $a, callable $c) {} exit(); readonly();');
	$names = $file->find(NameNode::class);
	Assert::same(['array', 'callable', 'readonly'], array_map(fn(NameNode $n) => $n->text, array_values(array_filter($names, fn(NameNode $n) => $n->isKeyword()))));
});
