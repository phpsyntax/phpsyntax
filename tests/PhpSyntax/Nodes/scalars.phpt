<?php declare(strict_types=1);

/**
 * The value of a literal: escape sequences, bases, heredoc bodies and writing a value back.
 */

use PhpSyntax\Node;
use PhpSyntax\Nodes\Expression\ArrayAccessNode;
use PhpSyntax\Nodes\Expression\ConstantFetchNode;
use PhpSyntax\Nodes\Scalar\BooleanNode;
use PhpSyntax\Nodes\Scalar\FloatNode;
use PhpSyntax\Nodes\Scalar\HeredocNode;
use PhpSyntax\Nodes\Scalar\IntegerNode;
use PhpSyntax\Nodes\Scalar\InterpolatedStringNode;
use PhpSyntax\Nodes\Scalar\NullNode;
use PhpSyntax\Nodes\Scalar\StringNode;
use PhpSyntax\Nodes\Scalar\UnquotedStringNode;
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use PhpSyntax\Parser;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


/**
 * The expression of the code, which must be of the given class.
 * @template T of Node
 * @param  class-string<T>  $class
 * @return T
 */
function literal(string $code, string $class): Node
{
	$stmt = (new Parser)->parse("<?php\n$code;\n")->statements->getItems()[0];
	assert($stmt instanceof ExpressionStatementNode);
	$expr = $stmt->expression;
	assert($expr instanceof $class);
	return $expr;
}


/**
 * The offset of "$a[x]", the first part of an interpolated string.
 * @template T of Node
 * @param  class-string<T>  $class
 * @return T
 */
function offset(string $code, string $class): Node
{
	$fetch = literal($code, InterpolatedStringNode::class)->parts->getItems()[0];
	assert($fetch instanceof ArrayAccessNode);
	$index = $fetch->index;
	assert($index instanceof $class);
	return $index;
}


test('a single-quoted string resolves only the two escapes it has', function () {
	Assert::same('a' . chr(92) . 'b', literal("'a" . str_repeat(chr(92), 2) . "b'", StringNode::class)->value);
	Assert::same("it's", literal("'it" . chr(92) . "'s'", StringNode::class)->value);
	Assert::same('a\tb', literal("'a" . chr(92) . "tb'", StringNode::class)->value);
	Assert::same("'", literal("'a'", StringNode::class)->quote);
});


test('a double-quoted string resolves the escapes of PHP', function () {
	Assert::same("a\tb\n", literal('"a\tb\n"', StringNode::class)->value);
	Assert::same('A' . chr(1) . '"$', literal('"\x41\1\"\$"', StringNode::class)->value);
	Assert::same("\u{1F600}\u{E9}", literal('"\u{1F600}\u{E9}"', StringNode::class)->value);
	Assert::same('\d', literal('"\d"', StringNode::class)->value); // not an escape sequence: the backslash stands for itself
	Assert::same('\x', literal('"\x"', StringNode::class)->value); // nor is a hex escape without a digit
	Assert::same('\xG', literal('"\xG"', StringNode::class)->value);
	Assert::same("\xA4", literal('"\xA4"', StringNode::class)->value);
	Assert::same("\x0A" . '4', literal('"\xA\x34"', StringNode::class)->value);
	Assert::same('"', literal('"a"', StringNode::class)->quote);
});


test('a binary prefix is not part of the value', function () {
	Assert::same('x', literal('b"x"', StringNode::class)->value);
	Assert::same('x', literal("B'x'", StringNode::class)->value);
	Assert::same('"', literal('b"x"', StringNode::class)->quote);
});


test('writing a value keeps the delimiter and escapes what it must', function () {
	$node = literal("'a'", StringNode::class);
	$node->setValue("it's " . chr(92) . ' here');
	Assert::same("'it\\'s \\\\ here'", $node->token->text);

	$node = literal('b"a"', StringNode::class);
	$node->setValue("x\ty\"");
	Assert::same('b"x\ty\""', $node->token->text);
	Assert::same("x\ty\"", $node->value);
});


test('a literal is made of a value, escaped the way the delimiter needs', function () {
	$value = 'C:' . chr(92) . 'dir' . chr(92) . "new\n\$x";
	Assert::same("'C:\\\\dir\\\\new\n\$x'", StringNode::fromValue($value)->token->text);
	Assert::same('"C:\\\\dir\\\\new\n\$x"', StringNode::fromValue($value, '"')->token->text);
	// what it writes is what it reads back, which is the whole point of writing it
	Assert::same($value, StringNode::fromValue($value)->value);
	Assert::same($value, StringNode::fromValue($value, '"')->value);
	Assert::null(StringNode::fromValue('a')->parent);
	Assert::exception(fn() => StringNode::fromValue('a', '`'), InvalidArgumentException::class, "A string is written with ' or \", not '`'.");
});


test('an offset written without quotes is a node of its own and stands for itself', function () {
	Assert::same('key', offset('"$a[key]"', UnquotedStringNode::class)->value);
	// what has quotes has them all the way: the only kinds a string is written in are ' and "
	Assert::same("'", literal("'a'", StringNode::class)->quote);
	Assert::same('"', literal('"a"', StringNode::class)->quote);
});


test('a numeric offset is an integer only where PHP reads one', function () {
	Assert::same(0, offset('"$a[0]"', IntegerNode::class)->value);
	Assert::same('01', offset('"$a[01]"', UnquotedStringNode::class)->value);
});


test('the value and the base of an integer', function () {
	foreach ([['1_000', 1000, 10], ['0x1F', 31, 16], ['0b1010', 10, 2], ['0o17', 15, 8], ['017', 15, 8], ['0', 0, 10]] as [$code, $value, $base]) {
		Assert::same($value, literal($code, IntegerNode::class)->value, $code);
		Assert::same($base, literal($code, IntegerNode::class)->base, $code);
	}
});


test('the value of a float', function () {
	Assert::same(1.5, literal('1.5', FloatNode::class)->value);
	Assert::same(1000.5, literal('1_000.5', FloatNode::class)->value);
	Assert::same(1.5E+3, literal('1.5e3', FloatNode::class)->value);

	// PHP reads a literal beyond the integer range as a float, whatever its base. Each expectation is the
	// same literal written again, so the test compares against the value PHP itself gives it; hexdec() and
	// its kin count the digits differently and would land one unit in the last place away.
	foreach ([
		'9223372036854775808' => 9_223_372_036_854_775_808,
		'0xFFFFFFFFFFFFFFFF' => 0xFFFFFFFFFFFFFFFF,
		'0x28938805d3ab99a57' => 0x28938805d3ab99a57,
		'0x5c349a2c547f9a190' => 0x5c349a2c547f9a190,
		'0o16645020726307760752333' => 0o16645020726307760752333,
		'0o72230425246031276567664' => 0o72230425246031276567664,
		'016645020726307760752333' => 016645020726307760752333,
		'0b10101011010001110100100101111100100001000011001100011001111011010' => 0b10101011010001110100100101111100100001000011001100011001111011010,
		'0b10011100111011100010001011001111111110001111010011011011111110000' => 0b10011100111011100010001011001111111110001111010011011011111110000,
	] as $code => $value) {
		Assert::same($value, literal($code, FloatNode::class)->value, $code);
	}
});


test('true, false and null are literals, in any letter case and behind a backslash', function () {
	Assert::true(literal('true', BooleanNode::class)->value);
	Assert::true(literal('TRUE', BooleanNode::class)->value);
	Assert::true(literal('\True', BooleanNode::class)->value);
	Assert::false(literal('false', BooleanNode::class)->value);
	Assert::same('\NULL', literal('\NULL', NullNode::class)->token->text);

	// a qualified or relative name of the same spelling is a constant PHP reads at run time
	literal('A\true', ConstantFetchNode::class);
	literal('namespace\null', ConstantFetchNode::class);
});


test('the body of a heredoc is outdented and ends before the closing delimiter', function () {
	$heredoc = literal("<<<TXT\n\ta\n\tb\n\tTXT", HeredocNode::class);
	Assert::same('TXT', $heredoc->label);
	Assert::same("\t", $heredoc->indentation);
	Assert::false($heredoc->isNowdoc());
	Assert::false($heredoc->hasInterpolation());
	Assert::same("a\nb", $heredoc->value);

	Assert::same('', literal("<<<TXT\nTXT", HeredocNode::class)->value);
	// a heredoc does not escape the double quote
	Assert::same("a\tb \" \$c", literal("<<<TXT\na\\tb \" \\\$c\nTXT", HeredocNode::class)->value);
});


test('the indentation of a heredoc is removed before the escapes are read', function () {
	// each expectation is what PHP itself reads the same body as: what an escape sequence makes is
	// no line of the source and keeps the spaces that follow it
	$value = fn(string $code) => literal($code, HeredocNode::class)->value;
	Assert::same("a\n    b", $value("<<<TXT\n    a\\n    b\n    TXT"));
	Assert::same("a\n\nb", $value("<<<TXT\n    a\n  \n    b\n    TXT")); // a blank line shorter than the indentation
	Assert::same("a\n  b", $value("<<<TXT\n  a\n    b\n  TXT"));
	Assert::same("a\\\nb", $value("<<<TXT\n    a\\\\\n    b\n    TXT"));
	Assert::same("\t", $value("<<<TXT\n    \\t\n    TXT"));
	Assert::same('\n    b', literal("<<<'TXT'\n    \\n    b\n    TXT", HeredocNode::class)->value);
});


test('a nowdoc resolves nothing', function () {
	$nowdoc = literal("<<<'TXT'\n\\n\\\\ \$a\nTXT", HeredocNode::class);
	Assert::true($nowdoc->isNowdoc());
	Assert::same('\n\\\ $a', $nowdoc->value);
});


test('an interpolating heredoc has no value of its own', function () {
	$heredoc = literal("<<<TXT\n\$a\nTXT", HeredocNode::class);
	Assert::true($heredoc->hasInterpolation());
	Assert::exception(fn() => $heredoc->value, LogicException::class, 'The heredoc interpolates, so it has no value of its own.');
});
