<?php declare(strict_types=1);

/**
 * Changing what a token says: a literal, a name, an identifier.
 *
 * Demonstrates: StringNode::setValue(), NameNode::$text, IdentifierNode::$text, Token::setText()
 * Usage:        php examples/mutation/text.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\Scalar\IntegerNode;
use PhpSyntax\Nodes\Scalar\StringNode;
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	namespace App;

	use Legacy\Mailer;

	class Notifier
	{
		public function notify(Mailer $mailer): void
		{
			$mailer->send("O'Brien <ob@example.com>", 0x1F);
		}
	}
	PHP);

$file = new Parser()->parse($code);

// a string literal: the value is written with the escaping its delimiter needs
$string = $file->find(StringNode::class)[0];
$string->setValue('Anna "Nan" Kral <ak@example.com>', "'");

// an integer literal: the token text is the notation, so writing it is writing the notation
$int = $file->find(IntegerNode::class)[0];
$int->token->setText('0b11111');

// a name: writing it re-tokenizes, so a qualified name does not stay an unqualified one
foreach ($file->find(NameNode::class) as $name) {
	if ($name->equals('Legacy\Mailer')) {
		$name->text = 'App\Mail\Mailer';
	}
}

// an identifier: a method name is checked to be an identifier and nothing else
$method = $file->find(MethodNode::class)[0];
$method->name->text = 'notifyCustomer';

printDiff($code, (string) $file);

// what the tree refuses, because it would put whitespace inside a token
try {
	$method->name->text = 'notify customer';
} catch (InvalidArgumentException $e) {
	echo "\n", 'InvalidArgumentException: ', $e->getMessage(), "\n";
}
