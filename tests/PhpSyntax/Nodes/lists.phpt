<?php declare(strict_types=1);

use PhpSyntax\Node;
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\MemberNode;
use PhpSyntax\Nodes\ModifiersNode;
use PhpSyntax\Nodes\NodeList;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\Statement\BlockNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\Statement\NamespaceNode;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Parser;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


final class StubNode extends Node
{
	public function __construct(
		public Token $token { set => $this->token = $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	public function getChildren(): array
	{
		return [$this->token];
	}


	public function replaceChild(Node|Token $old, Node|Token $new): void
	{
	}
}


function node(string $text): StubNode
{
	return new StubNode(new Token(TokenKind::Identifier, $text));
}


function comma(): Token
{
	return new Token(ord(','), ',');
}


test('NodeList: items, parents, iteration, mutation', function () {
	$list = new NodeList([$a = node('a'), $b = node('b')]);
	Assert::same($list, $a->parent);
	Assert::same([$a, $b], $list->getChildren());
	Assert::same('ab', (string) $list);

	$list->append($c = node('c'));
	$list->insert(0, $z = node('z'));
	Assert::same([$z, $a, $b, $c], $list->getItems());
	Assert::same($list, $z->parent);

	$list->removeItem($a);
	Assert::null($a->parent);
	$list->replaceChild($b, $x = node('x'));
	Assert::same([$z, $x, $c], $list->getItems());
	Assert::null($b->parent);
	Assert::same($list, $x->parent);
	Assert::same(1, $list->indexOf($x));

	Assert::exception(fn() => $list->append($x), LogicException::class, 'The node already belongs to a tree, clone it first.');
	Assert::exception(fn() => $list->indexOf($a), InvalidArgumentException::class, 'StubNode is not a child of PhpSyntax\Nodes\NodeList.');
});


test('a statement inserted into a file stands where its neighbor stands', function () {
	$parser = new Parser;

	// the blank line before the class belongs to the class and stays there
	$file = $parser->parse("<?php\nnamespace App;\n\nuse App\\Money;\n\nclass X\n{\n}\n");
	$namespace = $file->find(NamespaceNode::class)[0];
	$namespace->statements->insert(1, $parser->parseStatement('use App\Currency;'));
	Assert::same("<?php\nnamespace App;\n\nuse App\\Money;\nuse App\\Currency;\n\nclass X\n{\n}\n", (string) $file);

	// a member takes the indentation of the one above it
	$file = $parser->parse("<?php\nclass A\n{\n\tpublic function a() {}\n}\n");
	$file->find(ClassNode::class)[0]->members->append($parser->parseFragment(MemberNode::class, 'public function b() {}'));
	Assert::same("<?php\nclass A\n{\n\tpublic function a() {}\n\tpublic function b() {}\n}\n", (string) $file);

	// in a list written on one line the neighbor ends with a space, so the new item does too
	$file = $parser->parse('<?php function f() { a(); b(); }');
	$file->find(BlockNode::class)[0]->statements->append($parser->parseStatement('c();'));
	Assert::same('<?php function f() { a(); b(); c(); }', (string) $file);

	// what already ends its line inside its own text is given no line ending on top of it
	$file = $parser->parse("<?php\n\$a = 1;\n");
	$closing = $parser->parseStatement('?' . '>');
	$closing->getLastToken()?->setText("?>\n"); // a close tag keeps the line ending PHP swallows after it
	$file->statements->append($closing);
	Assert::same("<?php\n\$a = 1;\n?>\n", (string) $file);

	// an item that carries trivia of its own is taken as it is
	$file = $parser->parse("<?php\n\$a = 1;\n");
	$copy = clone $file->statements->getItems()[0];
	$copy->setEdgeTrivia(leading: []); // the open tag came with the copy
	$file->statements->append($copy);
	Assert::same("<?php\n\$a = 1;\n\$a = 1;\n", (string) $file);
});


test('SeparatedNodeList: separators between items and an optional trailing one', function () {
	$list = new SeparatedNodeList;
	Assert::true($list->isEmpty());
	Assert::false($list->hasTrailingSeparator());
	Assert::same('', (string) $list);

	$list->append($a = node('a'));
	$list->append($b = node('b'), $c1 = comma());
	Assert::same([$a, $b], $list->getItems());
	Assert::same([$c1], $list->getSeparators());
	Assert::same($list, $c1->parent);
	Assert::same('a,b', (string) $list);

	$list->setTrailingSeparator($c2 = comma());
	Assert::true($list->hasTrailingSeparator());
	Assert::same('a,b,', (string) $list);
	$list->setTrailingSeparator(null);
	Assert::same('a,b', (string) $list);
	Assert::null($c2->parent);

	$list->replaceChild($c1, $semicolon = new Token(ord(';'), ';'));
	Assert::same('a;b', (string) $list);
	$list->replaceChild($a, node('x'));
	Assert::same('x;b', (string) $list);

	$list->append(node('c'));
	Assert::same('x;b;c', (string) $list);
	Assert::exception(fn() => (new SeparatedNodeList)->append(node('c'), comma()), LogicException::class);
	Assert::exception(fn() => $list->replaceChild($semicolon, node('y')), InvalidArgumentException::class);
});


test('ModifiersNode', function () {
	$modifiers = new ModifiersNode([$public = new Token(TokenKind::Public, 'public')]);
	Assert::true($modifiers->has(TokenKind::Public));
	Assert::false($modifiers->has(TokenKind::Static));
	$modifiers->append($static = new Token(TokenKind::Static, 'static'));
	Assert::same('publicstatic', (string) $modifiers);
	$modifiers->removeToken($public);
	Assert::same([$static], $modifiers->getTokens());
	Assert::null($public->parent);
});


final class StubStatement extends StatementNode
{
	public function __construct(
		public Token $token { set => $this->token = $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	public function getChildren(): array
	{
		return [$this->token];
	}


	public function replaceChild(Node|Token $old, Node|Token $new): void
	{
	}
}


function stmt(string $text): StatementNode
{
	return new StubStatement(new Token(TokenKind::Identifier, $text));
}


test('FileNode counts mutations', function () {
	$stmt = stmt('a');
	$file = new FileNode(new NodeList([$stmt]), $eof = new Token(TokenKind::EndOfFile, ''));
	Assert::same($file, $stmt->getFile());
	Assert::same($file, $file->statements->parent);
	Assert::same(0, $file->revision);
	$file->statements->append(stmt('b'));
	Assert::same(1, $file->revision);
	$file->endOfFile = new Token(TokenKind::EndOfFile, '');
	Assert::same(2, $file->revision);
	Assert::null($eof->parent);
	Assert::null(node('x')->getFile());
});
