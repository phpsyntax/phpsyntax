<?php declare(strict_types=1);

use PhpSyntax\{Node, ParseException, Parser, Printer, Token};
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\Statement\HaltCompilerNode;
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
	Assert::same(Token::EndOfFile, $file->endOfFile->id);
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
	$halt = $file->statements[0];
	Assert::type(HaltCompilerNode::class, $halt);
	Assert::same(' raw data', $halt->data?->text);
	Assert::same($halt, $halt->data->parent);
	parse("<?php __halt_compiler() ?>\nraw");
	Assert::null(parse('<?php __halt_compiler();')->statements[0]->data ?? null);
});


test('newer syntax parses', function () {
	parse('<?php class A { public private(set) int $x { get => 1; } } $a |> f(...); (void) g(); echo __PROPERTY__;');
	parse('<?php function f((A&B)|null $x): static { return match(true) { default => new class {} }; }');
});


test('modifiers written without a space after them stay so', function () {
	parse('<?php class A { public static$a; var$b; public readonly?int $c; public private(set)?int $d; }');
	parse('<?php class A { function __construct(public$a, private readonly?int $b) {} }');
	parse('<?php class A { public int $p { final get=>1; } } final readonly class B {}');
	parse("<?php class A {\n\tprivate\nstatic\$a; }");
});


test('the parser keeps nothing of what it parsed', function () {
	$parser = new Parser;
	$file = WeakReference::create($parser->parse('<?php $a = f(1);'));
	gc_collect_cycles();
	Assert::null($file->get());
});


test('syntax errors', function () {
	$e = Assert::exception(fn() => parse("<?php\n\$a = ;"), ParseException::class, 'Unexpected `;`');
	Assert::type(ParseException::class, $e);
	Assert::same([2, 6, 11], [$e->sourceLine, $e->sourceColumn, $e->sourceOffset]);
	// a multi-byte line counts the column in characters, the offset in bytes
	$e = Assert::exception(fn() => parse("<?php\n\$á = ;"), ParseException::class, 'Unexpected `;`');
	Assert::type(ParseException::class, $e);
	Assert::same([2, 6, 12], [$e->sourceLine, $e->sourceColumn, $e->sourceOffset]);

	Assert::exception(fn() => parse('<?php $a'), ParseException::class, 'Unexpected end of file');
	Assert::exception(fn() => parse('<?php foo(1 2);'), ParseException::class, 'Unexpected `2`, expecting %a%');
	Assert::exception(fn() => parse('<?php class {}'), ParseException::class, 'Unexpected `{`, expecting an identifier');
	Assert::exception(fn() => parse('<?php $a `ls`;'), ParseException::class, 'Unexpected `` ` ``%a?%'); // a backtick is written in a longer run
	// an expected token is named by a word with its article or by its spelling
	Assert::exception(fn() => parse('<?php $a->;'), ParseException::class, 'Unexpected `;`, expecting an identifier, a variable, `{` or `$`');
	Assert::exception(fn() => parse('<?php new;'), ParseException::class, 'Unexpected `;`, expecting `abstract`, `final`, `readonly` or `class`');
	Assert::exception(fn() => parse("<?php\nfoo(); ?>\n<?php }"), ParseException::class, 'Unexpected `}`');
});
