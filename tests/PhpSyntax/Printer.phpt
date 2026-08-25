<?php declare(strict_types=1);

use PhpSyntax\Nodes\Statement\ReturnNode;
use PhpSyntax\Parser;
use PhpSyntax\Printer;
use PhpSyntax\Token;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('a substitute prints in place of the text of a token, the trivia and the tree staying as they are', function () {
	$code = "<?php\n\n// c\nreturn \$a + 1; // done\n";
	$file = (new Parser)->parse($code);
	$return = $file->statements->getItems()[0];
	Assert::type(ReturnNode::class, $return);
	$substitute = fn(Token $token): ?string => $token->is('$a') ? '$b' : null;

	Assert::same("<?php\n\n// c\nreturn \$b + 1; // done\n", Printer::print($file, $substitute));
	Assert::same("return \$b + 1;", Printer::printText($return, $substitute));
	Assert::same($code, Printer::print($file));
	Assert::same('$a', $return->expression?->getFirstToken()?->text);
});


test('printing a token alone writes its trivia around the substitute', function () {
	$file = (new Parser)->parse("<?php\n\$a ; // c\n");
	$token = $file->statements->getItems()[0]->getFirstToken();
	assert($token !== null);
	Assert::same("<?php\n\$a ", Printer::print($token)); // the open tag is its leading trivia
	Assert::same("<?php\n\$b ", Printer::print($token, fn(Token $token) => '$b'));
});


test('the text of a node is what printText() writes', function () {
	$file = (new Parser)->parse("<?php\n\n\tif (\$a) {\n\t\tf();\n\t} // c\n");
	$if = $file->statements->getItems()[0];
	Assert::same("if (\$a) {\n\t\tf();\n\t}", $if->text);
	Assert::same($if->text, Printer::printText($if));
	Assert::same('', Printer::printText(new PhpSyntax\Nodes\NodeList));
});
