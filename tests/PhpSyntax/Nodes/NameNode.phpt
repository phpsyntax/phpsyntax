<?php declare(strict_types=1);

use PhpSyntax\{Builder, NameForm, Parser};
use PhpSyntax\Nodes\{IdentifierNode, NameNode};
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


test('equals() ignores the letter case except for a constant', function () {
	$file = (new Parser)->parse('<?php use const A\BAR; use A\Baz; f(); FOO;');
	[$importedConstant, $importedClass, $function, $constant] = $file->find(NameNode::class);
	Assert::true($function->equals('F'));
	Assert::true($constant->equals('FOO'));
	Assert::false($constant->equals('foo'));

	Assert::true($importedConstant->equals('A\BAR'));
	Assert::false($importedConstant->equals('A\bar'));
	Assert::true($importedClass->equals('a\baz'));
});


test('IdentifierNode::equals() ignores the letter case where PHP does', function () {
	$file = (new Parser)->parse('<?php class Foo { const BAR = 1; public $prop { GET => 1; } function run() { $this->prop; $this->run(); self::BAR; self::CLASS; f(name: 1); } }');
	$ids = [];
	foreach ($file->find(IdentifierNode::class) as $id) {
		$ids[] = [$id->text, $id->equals(strtoupper($id->text)), $id->equals(strtolower($id->text))];
	}

	Assert::same([
		['Foo', true, true],    // a class
		['BAR', true, false],   // a constant
		['GET', true, true],    // a hook
		['run', true, true],    // a method
		['prop', false, true],  // a property
		['run', true, true],    // the call of a method
		['BAR', true, false],
		['CLASS', true, true],  // ::class
		['name', false, true],  // a named argument
	], $ids);
	Assert::false(IdentifierNode::fromText('run')->equals('RUN')); // standing nowhere
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
	assertRefused(fn() => $name->text = 'a b', InvalidArgumentException::class, '`a b` is not a name.', $name);
	assertRefused(fn() => $name->text = ' Foo', InvalidArgumentException::class, '` Foo` is not a name.', $name);
	assertRefused(fn() => $name->text = 'Foo ', InvalidArgumentException::class, '`Foo ` is not a name.', $name);
	assertRefused(fn() => $name->text = 'Foo /* c */', InvalidArgumentException::class, '`Foo /* c */` is not a name.', $name);
	assertRefused(fn() => $name->text = '', InvalidArgumentException::class, "`''` is not a name.", $name);

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
	Assert::same(NameForm::Qualified, NameNode::tryFromText('Foo\Bar')?->form);
	Assert::null(NameNode::tryFromText('a b'));
	Assert::null(NameNode::tryFromText('$a'));
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


test('role by the place in the tree', function () {
	$file = (new Parser)->parse('<?php namespace A; use B\C; use function D; f(E); new F; G::h(); function i(J $j): K {} $l instanceof M; #[N] class O extends P implements Q {} try {} catch (R $e) {}');
	$roles = $declarations = [];
	foreach ($file->find(NameNode::class) as $name) {
		$roles[$name->text] = $name->symbolKind->name;
		$declarations[$name->text] = $name->isDeclaration();
	}

	Assert::same([
		'A' => 'ClassLike',
		'B\C' => 'ClassLike',
		'D' => 'Function',
		'f' => 'Function',
		'E' => 'Constant',
		'F' => 'ClassLike',
		'G' => 'ClassLike',
		'J' => 'ClassLike',
		'K' => 'ClassLike',
		'M' => 'ClassLike',
		'N' => 'ClassLike',
		'P' => 'ClassLike',
		'Q' => 'ClassLike',
		'R' => 'ClassLike',
	], $roles);

	Assert::same(['A', 'B\C', 'D'], array_keys(array_filter($declarations)));
});


test('a name refers to a symbol unless it declares one, is a keyword, self, static or parent, or a builtin type', function () {
	$file = (new Parser)->parse("<?php\nnamespace A;\nuse B\\C;\nfunction f(int \$a, C \$b, self \$c, array \$d): ?C {}\nnew C; int::x(); static::y(); self(); echo parent;");
	Assert::same([
		['A', false],
		['B\C', false],
		['int', false],
		['C', true],
		['self', false],
		['array', false],
		['C', true],
		['C', true],
		['int', true], // a class of that name, however hopeless, is what PHP would look up
		['static', false],
		['self', true], // a function of that name, which PHP allows
		['parent', true], // and a constant
	], array_map(fn(NameNode $name) => [$name->text, $name->isReference()], $file->find(NameNode::class)));
});
