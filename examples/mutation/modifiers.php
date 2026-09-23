<?php declare(strict_types=1);

/**
 * Adding and removing modifiers: `final`, `static`, a visibility. The open tag, the doc comments and
 * the comments between the keywords stay where they belong.
 *
 * Demonstrates: `ModifiersNode::append()`, `removeToken()`, `findToken()`, `has()`, `new Token()`
 * Usage:        php examples/mutation/modifiers.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Parser;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;

$code = sample(<<<'PHP'
	<?php
	/** Sends the mail of the shop. */
	class Mailer
	{
		public static /* kept for the old client */ function send(): void {}

		/** Puts the mail in the queue. */
		function queue(): void {}
	}
	PHP);

$file = new Parser()->parse($code);

// the class becomes final; being its first modifier, the keyword opens the declaration, so the open
// tag and the doc comment stay in front of it
$class = must($file->findFirst(ClassNode::class));
if (!$class->modifiers->has(TokenKind::Final)) {
	$class->modifiers->append(new Token(TokenKind::Final, 'final'));
}

foreach ($file->find(MethodNode::class) as $method) {
	// a static method becomes an instance one; the comment that followed the keyword stays in the code
	if ($static = $method->modifiers->findToken(TokenKind::Static)) {
		$method->modifiers->removeToken($static);
	}

	// a method without a visibility gets one, in front of the keyword it now opens the line with
	if ($method->modifiers->visibility === null) {
		$method->modifiers->append(new Token(TokenKind::Public, 'public'));
	}
}

printDiff($code, (string) $file);

echo "\n--- the result ---\n", (string) $file, "\n";
