<?php declare(strict_types=1);

use PhpSyntax\{Builder, ParseException, Token};
use PhpSyntax\Nodes\{ArgumentNode, ArrayItemNode, AttributeGroupNode, MatchArmNode, MemberNode, ParameterNode, UseItemNode};
use PhpSyntax\Nodes\Expression\BinaryOpNode;
use PhpSyntax\Nodes\Member\PropertyNode;
use PhpSyntax\Nodes\Statement\IfNode;
use PhpSyntax\Nodes\Type\UnionTypeNode;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


test('expression(): detached, without positions, with empty trivia on the edges', function () {
	$expr = (new Builder)->expression('$a + 1 // c');
	Assert::type(BinaryOpNode::class, $expr);
	Assert::null($expr->parent);
	Assert::same('$a + 1', (string) $expr);
	$first = $expr->getFirstToken();
	Assert::same([], $first->leadingTrivia);
	Assert::same([], $expr->getLastToken()->trailingTrivia);
	Assert::same(-1, $first->line);
	Assert::same(-1, $first->pos);
	Assert::null($expr->getStartLine());
	Assert::same(' ', $expr->operator->trailingTrivia[0]->text);

	// an error is told in the terms of the fragment, never of the code it is wrapped in
	$e = Assert::exception(fn() => (new Builder)->expression("\$a\n+"), ParseException::class, 'Unexpected end of the fragment');
	Assert::type(ParseException::class, $e);
	Assert::same([2, 2, 4], [$e->sourceLine, $e->sourceColumn, $e->sourceOffset]);
	Assert::exception(fn() => (new Builder)->expression('[1, 2'), ParseException::class, 'Unexpected end of the fragment, expecting `]`');
	$e = Assert::exception(fn() => (new Builder)->expression('f(1 2)'), ParseException::class, 'Unexpected `2`, expecting `)`');
	Assert::type(ParseException::class, $e);
	Assert::same([1, 5, 4], [$e->sourceLine, $e->sourceColumn, $e->sourceOffset]);
	$e = Assert::exception(fn() => (new Builder)->expression('"abc'), ParseException::class, 'Unterminated string');
	Assert::type(ParseException::class, $e);
	Assert::same([1, 1, 0], [$e->sourceLine, $e->sourceColumn, $e->sourceOffset]);
	Assert::exception(fn() => (new Builder)->expression('if (1) {}'), ParseException::class, '`if (1) {}` is not a single expression.');
	// what parses but leaves something over is no fragment either, whatever the kind
	Assert::exception(fn() => (new Builder)->expression('$a; $b'), ParseException::class, '`$a; $b` is not a single expression.');
});


test('statement()', function () {
	$stmt = (new Builder)->statement("if (\$a) {\n\tb();\n}\n");
	Assert::type(IfNode::class, $stmt);
	Assert::same("if (\$a) {\n\tb();\n}", (string) $stmt);
	Assert::exception(fn() => (new Builder)->statement('a(); b();'), ParseException::class, '`a(); b();` is not a single statement.');
	Assert::exception(fn() => (new Builder)->statement(''), ParseException::class, "`''` is not a single statement.");
});


test('type() and name()', function () {
	$type = (new Builder)->type('int|(A&B)|null');
	Assert::type(UnionTypeNode::class, $type);
	Assert::same('int|(A&B)|null', (string) $type);
	Assert::exception(fn() => (new Builder)->type('1'), ParseException::class, 'Unexpected `1`');

	$name = (new Builder)->name('\Foo\Bar');
	Assert::same(Token::NameFullyQualified, $name->token->id);
	Assert::same('static', (string) (new Builder)->name('static'));
	Assert::exception(fn() => (new Builder)->name('$a'), ParseException::class, '`$a` is not a single name.');
});


test('fragment(): an item of a list is parsed in the code such an item stands in', function () {
	$builder = new Builder;

	$member = $builder->fragment(MemberNode::class, 'public readonly int $price;');
	Assert::type(PropertyNode::class, $member);
	Assert::same('public readonly int $price;', (string) $member);
	Assert::null($member->parent);

	$parameter = $builder->fragment(ParameterNode::class, "private string \$currency = 'EUR'");
	Assert::same("private string \$currency = 'EUR'", (string) $parameter);
	Assert::true($parameter->promoted);

	Assert::same("'utf8mb4'", (string) $builder->fragment(ArgumentNode::class, "'utf8mb4'"));
	Assert::same("'port' => 3306", (string) $builder->fragment(ArrayItemNode::class, "'port' => 3306"));
	Assert::same('Shop\Money as M', (string) $builder->fragment(UseItemNode::class, 'Shop\Money as M'));
	Assert::same('1, 2 => f()', (string) $builder->fragment(MatchArmNode::class, '1, 2 => f()'));
	Assert::same('#[Attr(1)]', (string) $builder->fragment(AttributeGroupNode::class, '#[Attr(1)]'));

	// the class may be any node the wrapped code can hold, not only the kind the wrapper is named after
	Assert::same('$a + 1', (string) $builder->fragment(BinaryOpNode::class, '$a + 1'));

	Assert::exception(
		fn() => $builder->fragment(ParameterNode::class, '$a, $b'),
		ParseException::class,
		'`$a, $b` is not a single parameter.',
	);
	Assert::exception(
		fn() => $builder->fragment(ArrayItemNode::class, '1, 2'),
		ParseException::class,
		'`1, 2` is not a single array item.',
	);
	Assert::exception(
		fn() => $builder->fragment(PhpSyntax\Nodes\FileNode::class, '$a;'),
		InvalidArgumentException::class,
		'A node of `PhpSyntax\\Nodes\\FileNode` cannot be parsed as a fragment of its own.',
	);
});
