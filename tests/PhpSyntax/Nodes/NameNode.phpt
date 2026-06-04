<?php declare(strict_types=1);

use PhpSyntax\NameForm;
use PhpSyntax\Nodes\NameNode;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


test('a name built from text is written the way the text says', function () {
	Assert::same(NameForm::Unqualified, NameNode::fromText('Foo')->form);
	Assert::same(NameForm::Qualified, NameNode::fromText('Foo\Bar')->form);
	Assert::same(NameForm::FullyQualified, NameNode::fromText('\Foo\Bar')->form);
	Assert::same('\Foo\Bar', (string) NameNode::fromText('\Foo\Bar'));
	Assert::true(NameNode::fromText('static')->isKeyword());
	Assert::exception(fn() => NameNode::fromText('a b'), InvalidArgumentException::class, '`a b` is not a name.');
});
