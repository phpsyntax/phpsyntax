<?php declare(strict_types=1);

use PhpSyntax\Nodes\ArgumentNode;
use PhpSyntax\Nodes\ArrayItemNode;
use PhpSyntax\Nodes\AttributeGroupNode;
use PhpSyntax\Nodes\Expression\BinaryOpNode;
use PhpSyntax\Nodes\MatchArmNode;
use PhpSyntax\Nodes\Member\PropertyNode;
use PhpSyntax\Nodes\MemberNode;
use PhpSyntax\Nodes\ParameterNode;
use PhpSyntax\Nodes\Statement\IfNode;
use PhpSyntax\Nodes\Type\UnionTypeNode;
use PhpSyntax\Nodes\UseItemNode;
use PhpSyntax\ParseException;
use PhpSyntax\Parser;
use PhpSyntax\TokenKind;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


test('parseExpression: detached, without positions, with empty trivia on the edges', function () {
	$expr = (new Parser)->parseExpression('$a + 1 // c');
	Assert::type(BinaryOpNode::class, $expr);
	Assert::null($expr->parent);
	Assert::same('$a + 1', (string) $expr);
	$first = $expr->getFirstToken();
	Assert::type(PhpSyntax\Token::class, $first);
	Assert::same([], $first->leadingTrivia);
	Assert::same([], $expr->getLastToken()?->trailingTrivia);
	Assert::null($first->originalLine);
	Assert::null($first->originalOffset);
	Assert::null($expr->getStartLine());
	Assert::same(' ', $expr->operator->trailingTrivia[0]->text);

	Assert::exception(fn() => (new Parser)->parseExpression('$a +'), ParseException::class, "Unexpected ';'");
	Assert::exception(fn() => (new Parser)->parseExpression('if (1) {}'), ParseException::class, 'The code is not a single expression.');
	// what parses but leaves something over is no fragment either, whatever the kind
	Assert::exception(fn() => (new Parser)->parseExpression('$a; $b'), ParseException::class, 'The code is not a single expression.');
});


test('parseStatement', function () {
	$stmt = (new Parser)->parseStatement("if (\$a) {\n\tb();\n}\n");
	Assert::type(IfNode::class, $stmt);
	Assert::same("if (\$a) {\n\tb();\n}", (string) $stmt);
	Assert::exception(fn() => (new Parser)->parseStatement('a(); b();'), ParseException::class, 'The code is not a single statement.');
	Assert::exception(fn() => (new Parser)->parseStatement(''), ParseException::class, 'The code is not a single statement.');
});


test('parseType and parseName', function () {
	$type = (new Parser)->parseType('int|(A&B)|null');
	Assert::type(UnionTypeNode::class, $type);
	Assert::same('int|(A&B)|null', (string) $type);
	Assert::exception(fn() => (new Parser)->parseType('1'), ParseException::class, "Unexpected '1'");

	$name = (new Parser)->parseName('\Foo\Bar');
	Assert::same(TokenKind::NameFullyQualified, $name->token->kind);
	Assert::same('static', (string) (new Parser)->parseName('static'));
	Assert::exception(fn() => (new Parser)->parseName('$a'), ParseException::class, 'The code is not a single name.');
});


test('parseFragment: an item of a list is parsed in the code such an item stands in', function () {
	$parser = new Parser;

	$member = $parser->parseFragment(MemberNode::class, 'public readonly int $price;');
	Assert::type(PropertyNode::class, $member);
	Assert::same('public readonly int $price;', (string) $member);
	Assert::null($member->parent);

	$parameter = $parser->parseFragment(ParameterNode::class, "private string \$currency = 'EUR'");
	Assert::same("private string \$currency = 'EUR'", (string) $parameter);
	Assert::true($parameter->isPromoted());

	Assert::same("'utf8mb4'", (string) $parser->parseFragment(ArgumentNode::class, "'utf8mb4'"));
	Assert::same("'port' => 3306", (string) $parser->parseFragment(ArrayItemNode::class, "'port' => 3306"));
	Assert::same('Shop\Money as M', (string) $parser->parseFragment(UseItemNode::class, 'Shop\Money as M'));
	Assert::same('1, 2 => f()', (string) $parser->parseFragment(MatchArmNode::class, '1, 2 => f()'));
	Assert::same('#[Attr(1)]', (string) $parser->parseFragment(AttributeGroupNode::class, '#[Attr(1)]'));

	// the class may be any node the wrapped code can hold, not only the kind the wrapper is named after
	Assert::same('$a + 1', (string) $parser->parseFragment(BinaryOpNode::class, '$a + 1'));

	Assert::exception(
		fn() => $parser->parseFragment(ParameterNode::class, '$a, $b'),
		ParseException::class,
		'The code is not a single parameter.',
	);
	Assert::exception(
		fn() => $parser->parseFragment(ArrayItemNode::class, '1, 2'),
		ParseException::class,
		'The code is not a single array item.',
	);
	Assert::exception(
		fn() => $parser->parseFragment(PhpSyntax\Nodes\FileNode::class, '$a;'),
		InvalidArgumentException::class,
		"There is no code a node of 'PhpSyntax\\Nodes\\FileNode' could be parsed in.",
	);
});
