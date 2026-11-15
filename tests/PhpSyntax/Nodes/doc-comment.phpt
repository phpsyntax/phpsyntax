<?php declare(strict_types=1);

use PhpSyntax\{Parser, Trivia};
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


test('doc comment above the node, or after the previous token', function () {
	$file = (new Parser)->parse("<?php\nclass A {\n\t/** @var int */\n\tpublic \$a; /** @var string */ public \$b;\n\tpublic \$c;\n}\n");
	$class = $file->statements[0];
	assert($class instanceof PhpSyntax\Nodes\Statement\ClassNode);
	[$a, $b, $c] = $class->members->getItems();
	Assert::same('/** @var int */', $a->getDocComment()?->text);
	Assert::same('/** @var string */', $b->getDocComment()?->text);
	Assert::null($c->getDocComment());
	Assert::same(Trivia::DocComment, $a->getDocComment()->id);

	$a->replaceDocComment(new Trivia(Trivia::DocComment, '/** @var float */'));
	$b->removeDocComment();
	Assert::same("<?php\nclass A {\n\t/** @var float */\n\tpublic \$a; public \$b;\n\tpublic \$c;\n}\n", (string) $file);

	$a->removeDocComment();
	Assert::same("<?php\nclass A {\n\tpublic \$a; public \$b;\n\tpublic \$c;\n}\n", (string) $file);
	Assert::exception($c->removeDocComment(...), LogicException::class, 'The node has no doc comment.');
});


test('doc comment of a parameter after it, before the separator, as PHP reads it first', function () {
	$file = (new Parser)->parse("<?php\nfunction f(\n\t/** before */ string \$a /** after */,\n\tstring \$b\n\t/** next line */,\n\tstring \$c, /** of nobody */\n\t/** before d */ string \$d\n) {}\n");
	$function = $file->statements[0];
	assert($function instanceof PhpSyntax\Nodes\Statement\FunctionNode);
	[$a, $b, $c, $d] = $function->parameters->getItems();
	Assert::same('/** after */', $a->getDocComment()?->text);
	Assert::same('/** next line */', $b->getDocComment()?->text);
	Assert::null($c->getDocComment());
	Assert::same('/** before d */', $d->getDocComment()?->text);

	$a->removeDocComment();
	$b->replaceDocComment(new Trivia(Trivia::DocComment, '/** the b */'));
	Assert::same(
		"<?php\nfunction f(\n\t/** before */ string \$a,\n\tstring \$b\n\t/** the b */,\n\tstring \$c, /** of nobody */\n\t/** before d */ string \$d\n) {}\n",
		(string) $file,
	);
	Assert::same('/** before */', $a->getDocComment()?->text);
});


test('a doc comment inside string interpolation documents nothing', function () {
	$file = (new Parser)->parse("<?php \"{\$x[/** c */ 1]}\";\n");
	Assert::null($file->find(PhpSyntax\Nodes\Scalar\IntegerNode::class)[0]->getDocComment());
});


test('a plain comment between the doc comment and the node does not hide it, a list without tokens has none', function () {
	$file = (new Parser)->parse("<?php\n/** a */\n// x\n\$a;");
	Assert::same('/** a */', $file->statements[0]->getDocComment()?->text);
	Assert::null((new PhpSyntax\Nodes\PlainNodeList)->getDocComment());
});
