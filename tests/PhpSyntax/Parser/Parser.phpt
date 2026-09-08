<?php declare(strict_types=1);

use PhpSyntax\Node;
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\Statement\HaltCompilerNode;
use PhpSyntax\ParseException;
use PhpSyntax\Parser;
use PhpSyntax\Printer;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


function parse(string $code): FileNode
{
	$file = (new Parser)->parse($code);
	Assert::same($code, Printer::print($file), 'round-trip');
	Assert::same($code, (string) $file);
	return $file;
}


test('the tree keeps every token and sets parents', function () {
	$file = parse("<?php\n\$a = f(1, 2); // c\n");
	Assert::null($file->parent);
	Assert::same(TokenKind::EndOfFile, $file->endOfFile->kind);
	Assert::same($file, $file->endOfFile->parent);

	$tokens = [];
	$walk = function (Node|Token $node) use (&$walk, &$tokens) {
		if ($node instanceof Token) {
			$tokens[] = $node->text;
		} else {
			foreach ($node->getChildren() as $child) {
				Assert::same($node, $child->parent);
				$walk($child);
			}
		}
	};
	$walk($file);
	Assert::same(['$a', '=', 'f', '(', '1', ',', '2', ')', ';', ''], $tokens);
});


test('empty file, file without PHP, close tag as a statement terminator', function () {
	Assert::count(0, parse('')->statements->getItems());
	Assert::count(1, parse('x')->statements->getItems());
	parse("<?php foo() ?>\n<b><?php bar(); ?>");
	parse('<?= $a ?>');
	parse('<?php if ($a): ?>x<?php endif ?>');
});


test('__halt_compiler data are part of the tree', function () {
	$file = parse('<?php __halt_compiler(); raw data');
	$halt = $file->statements->getItems()[0];
	Assert::type(HaltCompilerNode::class, $halt);
	Assert::same(' raw data', $halt->data?->text);
	Assert::same($halt, $halt->data->parent);
	parse("<?php __halt_compiler() ?>\nraw");
	Assert::null(parse('<?php __halt_compiler();')->statements->getItems()[0]->data ?? null);
});


test('newer syntax parses', function () {
	parse('<?php class A { public private(set) int $x { get => 1; } } $a |> f(...); (void) g(); echo __PROPERTY__;');
	parse('<?php function f((A&B)|null $x): static { return match(true) { default => new class {} }; }');
});


test('syntax errors', function () {
	$e = Assert::exception(fn() => parse("<?php\n\$a = ;"), ParseException::class, "Unexpected ';'");
	Assert::type(ParseException::class, $e);
	Assert::same([2, 6, 11], [$e->sourceLine, $e->sourceColumn, $e->sourceOffset]);
	// a multi-byte line counts the column in characters, the offset in bytes
	$e = Assert::exception(fn() => parse("<?php\n\$á = ;"), ParseException::class, "Unexpected ';'");
	Assert::type(ParseException::class, $e);
	Assert::same([2, 6, 12], [$e->sourceLine, $e->sourceColumn, $e->sourceOffset]);

	Assert::exception(fn() => parse('<?php $a'), ParseException::class, 'Unexpected end of file');
	Assert::exception(fn() => parse('<?php foo(1 2);'), ParseException::class, "Unexpected '2', expecting %a%");
	Assert::exception(fn() => parse('<?php class {}'), ParseException::class, "Unexpected '{', expecting T_STRING");
	Assert::exception(fn() => parse("<?php\nfoo(); ?>\n<?php }"), ParseException::class, "Unexpected '}'");
});
