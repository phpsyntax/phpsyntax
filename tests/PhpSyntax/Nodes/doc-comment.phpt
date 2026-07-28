<?php declare(strict_types=1);

use PhpSyntax\Parser;
use PhpSyntax\Trivia;
use PhpSyntax\TriviaKind;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


test('doc comment above the node, or after the previous token', function () {
	$file = (new Parser)->parse("<?php\nclass A {\n\t/** @var int */\n\tpublic \$a; /** @var string */ public \$b;\n\tpublic \$c;\n}\n");
	$class = $file->statements->getItems()[0];
	assert($class instanceof PhpSyntax\Nodes\Statement\ClassNode);
	[$a, $b, $c] = $class->members->getItems();
	Assert::same('/** @var int */', $a->getDocComment()?->text);
	Assert::same('/** @var string */', $b->getDocComment()?->text);
	Assert::null($c->getDocComment());

	$a->replaceDocComment(new Trivia(TriviaKind::DocComment, '/** @var float */'));
	$b->removeDocComment();
	Assert::same("<?php\nclass A {\n\t/** @var float */\n\tpublic \$a; public \$b;\n\tpublic \$c;\n}\n", (string) $file);

	$a->removeDocComment();
	Assert::same("<?php\nclass A {\n\tpublic \$a; public \$b;\n\tpublic \$c;\n}\n", (string) $file);
	Assert::exception(fn() => $c->removeDocComment(), LogicException::class, 'The node has no doc comment.');
});
