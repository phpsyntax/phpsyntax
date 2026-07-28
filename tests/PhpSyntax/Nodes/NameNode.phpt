<?php declare(strict_types=1);

use PhpSyntax\NameKind;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Parser;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


function name(string $code): NameNode
{
	return (new Parser)->parseName($code);
}


test('kinds and parts', function () {
	$name = name('Foo');
	Assert::same(NameKind::Unqualified, $name->kind);
	Assert::same(['Foo'], $name->parts);
	Assert::same('Foo', $name->text);

	$name = name('Foo\Bar');
	Assert::same(NameKind::Qualified, $name->kind);
	Assert::same(['Foo', 'Bar'], $name->parts);

	$name = name('\Foo\Bar');
	Assert::same(NameKind::FullyQualified, $name->kind);
	Assert::same(['Foo', 'Bar'], $name->parts);
	Assert::same('\Foo\Bar', $name->text);

	$name = name('namespace\Bar');
	Assert::same(NameKind::Relative, $name->kind);
	Assert::same(['Bar'], $name->parts);
});


test('the short name, the qualification and the special class names', function () {
	Assert::same('Bar', name('Foo\Bar')->shortName);
	Assert::same('Foo', name('Foo')->shortName);
	Assert::true(name('\Foo\Bar')->isFullyQualified());
	Assert::false(name('Foo\Bar')->isFullyQualified());
	Assert::true(name('Foo')->isUnqualified());
	Assert::false(name('\Foo')->isUnqualified());
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


test('writing a name replaces the token with the one it is written as', function () {
	$name = name('Foo');
	$name->text = 'Bar\Baz';
	Assert::same('Bar\Baz', $name->text);
	Assert::same(NameKind::Qualified, $name->kind);
	Assert::same('Bar\Baz', (string) $name);

	$name->text = '\Qux';
	Assert::same(NameKind::FullyQualified, $name->kind);
	$name->text = 'static';
	Assert::true($name->isKeyword());
	$name->text = 'Foo';
	Assert::false($name->isKeyword());

	// the name must be the whole token: no whitespace and no comment may reach the text of a token
	Assert::exception(fn() => $name->text = 'a b', InvalidArgumentException::class, "'a b' is not a name.");
	Assert::exception(fn() => $name->text = ' Foo', InvalidArgumentException::class, "' Foo' is not a name.");
	Assert::exception(fn() => $name->text = 'Foo ', InvalidArgumentException::class, "'Foo ' is not a name.");
	Assert::exception(fn() => $name->text = 'Foo /* c */', InvalidArgumentException::class, "'Foo /* c */' is not a name.");
	Assert::exception(fn() => $name->text = '', InvalidArgumentException::class, "'' is not a name.");
});


test('a name built from text is written the way the text says', function () {
	Assert::same(NameKind::Unqualified, NameNode::fromText('Foo')->kind);
	Assert::same(NameKind::Qualified, NameNode::fromText('Foo\Bar')->kind);
	Assert::same(NameKind::FullyQualified, NameNode::fromText('\Foo\Bar')->kind);
	Assert::same('\Foo\Bar', (string) NameNode::fromText('\Foo\Bar'));
	Assert::true(NameNode::fromText('static')->isKeyword());
	Assert::exception(fn() => NameNode::fromText('a b'), InvalidArgumentException::class, "'a b' is not a name.");
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
		$roles[$name->text] = $name->role->name;
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
