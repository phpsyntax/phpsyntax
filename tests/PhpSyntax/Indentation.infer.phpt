<?php declare(strict_types=1);

use PhpSyntax\{Indentation, LayoutRole, Node, Parser, Style, Token};
use PhpSyntax\Nodes\Expression\{ArrayNode, BinaryOpNode};
use PhpSyntax\Nodes\{FileNode, PlainNodeList, SeparatedNodeList};
use PhpSyntax\Nodes\Statement\{BlockNode, EchoNode};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function parse(string $code): FileNode
{
	return (new Parser)->parse($code);
}


/** The first token of the file with the text. */
function tokenOf(FileNode $file, string $text): Token
{
	foreach ($file->getIndex()->getTokens() as $token) {
		if ($token->text === $text) {
			return $token;
		}
	}

	throw new LogicException("No token '$text' in the code.");
}


/**
 * The indentation the tokens opening a line have, against the one their place in the tree gives them.
 * @return list<array{?int, string, string}>
 */
function compare(string $code, Style $style): array
{
	$result = [];
	foreach (parse($code)->getIndex()->getTokens() as $token) {
		if (Indentation::opensLine($token)) {
			$result[] = [$token->getCurrentLine(), $token->getIndentation(), Indentation::infer($token, $style)];
		}
	}

	return $result;
}


$code = <<<'XX'
	<?php

	namespace App;

	use A\B;

	abstract class C extends B implements I
	{
		public const X = [
			1,
			2,
		];

		public function m(
			int $a,
			string $b,
		): array {
			if ($a
				&& $b
			) {
				foreach ($this->items as $k => $v) {
					echo $k;
				}
			} elseif ($b) {
				$x = $a
					? 1
					: 2;
			} else {
				$this->one()
					->two()
					->three();
			}

			if ($a)
				return [
					'k' => $b,
				];

			switch ($a) {
				case 1:
					return [];

				default:
					break;
			}

			return match ($a) {
				1 => 'a',
				default => 'b',
			};
		}
	}

	XX;


test('code written in the convention infers the indentation it already has', function () use ($code) {
	$rows = compare($code, new Style);
	Assert::count(44, $rows);
	foreach ($rows as [$line, $actual, $inferred]) {
		Assert::same($actual, $inferred, "line $line");
	}

	$spaces = (new Style)->withIndent('    ');
	$widened = (string) preg_replace_callback('~^\t+~m', fn($m) => str_repeat('    ', strlen($m[0])), $code);
	$rows = compare($widened, $spaces);
	Assert::count(44, $rows);
	foreach ($rows as [$line, $actual, $inferred]) {
		Assert::same($actual, $inferred, "line $line");
	}
});


test('findOwner() is the lowest ancestor the token does not begin and what the token is to it', function () {
	$file = parse("<?php\nif (\$a) {\n\techo 1;\n\techo 2;\n}\n");
	Assert::null(Indentation::findOwner(tokenOf($file, 'if'))); // the first token of the file begins everything above it

	$echo = tokenOf($file, 'echo');
	$owner = Indentation::findOwner($echo);
	Assert::type(BlockNode::class, $owner?->node);
	Assert::type(PlainNodeList::class, $owner->child); // a list stands for the item that holds the token
	Assert::same(LayoutRole::Content, $owner->role);
	Assert::type(EchoNode::class, $owner->item);
	Assert::same($echo, $owner->item->getFirstToken());

	// the closing brace is what closes the block, not part of its content
	$owner = Indentation::findOwner(tokenOf($file, '}'));
	Assert::type(BlockNode::class, $owner?->node);
	Assert::same(LayoutRole::Closer, $owner->role);

	// a separator stands for the first item of its list
	$owner = Indentation::findOwner(tokenOf(parse("<?php\n\$a = [\n\t1\n\t, 2,\n];\n"), ','));
	Assert::type(ArrayNode::class, $owner?->node);
	Assert::type(SeparatedNodeList::class, $owner->child);
	Assert::same(LayoutRole::Content, $owner->role);
	Assert::same('1', $owner->item instanceof Node ? $owner->item->text : null);
});


test('infer() counts the content of a body from the line its structure begins on, wherever the brace stands', function () {
	$style = new Style;
	$file = parse("<?php\nif (\$a)\n\t{\n\t\techo 1;\n\t}\n");
	Assert::same('', Indentation::infer(tokenOf($file, '{'), $style)); // the brace stands where the if stands
	Assert::same("\t", Indentation::infer(tokenOf($file, 'echo'), $style));
	Assert::same('', Indentation::infer(tokenOf($file, '}'), $style));

	// a bare statement steps in, a block does not
	Assert::same("\t", Indentation::infer(tokenOf(parse("<?php\nif (\$a)\n\techo 1;\n"), 'echo'), $style));
	Assert::same('', Indentation::infer(tokenOf(parse("<?php\nif (\$a)\n{\n}\n"), '{'), $style));
});


test('infer() steps into a namespace only where it is written with braces', function () {
	$style = new Style;
	Assert::same('', Indentation::infer(tokenOf(parse("<?php\nnamespace A;\n\nclass B\n{\n}\n"), 'class'), $style));
	Assert::same("\t", Indentation::infer(tokenOf(parse("<?php\nnamespace A {\n\tclass B\n\t{\n\t}\n}\n"), 'class'), $style));
});


test('infer() lines an operator up with the line its expression begins on', function () {
	$style = new Style;
	// the expression shares its first line with what holds it, so the operator steps in
	Assert::same("\t", Indentation::infer(tokenOf(parse("<?php\n\$a = 1\n\t+ 2;\n"), '+'), $style));

	// here it has a line of its own to line up with, so the same indentation comes of level zero
	Assert::same("\t", Indentation::infer(tokenOf(parse("<?php\nfoo(\n\t1\n\t+ 2,\n);\n"), '+'), $style));

	// the line of the expression is the line of the statement, so lining up with it would say nothing
	Assert::same("\t", Indentation::infer(tokenOf(parse("<?php\n\$a\n\t&& b();\n"), '&&'), $style));
});


test('infer() takes a pipeline for a chain, not for a computation', function () {
	$file = parse("<?php\n\$a\n\t|> f(...);\n");
	$pipe = tokenOf($file, '|>');
	$owner = Indentation::findOwner($pipe);
	Assert::type(BinaryOpNode::class, $owner?->node);
	Assert::same(LayoutRole::Link, $owner->role);
	Assert::same("\t", Indentation::infer($pipe, new Style));
});


test('infer() gives the first token of the file no indentation', function () {
	Assert::same('', Indentation::infer(tokenOf(parse("<?php\n\techo 1;\n"), 'echo'), new Style));
});
