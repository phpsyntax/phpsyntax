<?php declare(strict_types=1);

/**
 * The value of a literal: escape sequences, bases, heredoc bodies and writing a value back.
 */

use PhpSyntax\Nodes\Scalar\StringNode;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

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
