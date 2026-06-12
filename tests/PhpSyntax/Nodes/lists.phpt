<?php declare(strict_types=1);

use PhpSyntax\{Builder, Node, Parser, Token, Trivia};
use PhpSyntax\Nodes\{FileNode, MemberNode, ModifiersNode, NodeList, PlainNodeList, SeparatedNodeList, StatementNode};
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Statement\{BlockNode, ClassNode, NamespaceNode};
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
	return new StubNode(new Token(Token::Identifier, $text));
}


function comma(): Token
{
	return new Token(ord(','), ',');
}


test('PlainNodeList: items, parents, iteration, mutation', function () {
	$list = new PlainNodeList([$a = node('a'), $b = node('b')]);
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

	Assert::exception(fn() => $list->append($x), LogicException::class, 'The node already belongs to a tree, `clone` it first.');
	Assert::exception(fn() => $list->indexOf($a), InvalidArgumentException::class, '`StubNode` is not a child of `PhpSyntax\Nodes\PlainNodeList`.');
});


test('a list is read as a collection and written by its methods alone', function () {
	$lists = [
		fn(Node $a, Node $b): NodeList => new PlainNodeList([$a, $b]),
		fn(Node $a, Node $b): NodeList => new SeparatedNodeList([$a, $b], [comma()]),
	];
	foreach ($lists as $create) {
		$list = $create($a = node('a'), $b = node('b'));
		Assert::same($a, $list[0]);
		Assert::same($b, $list[1]);
		Assert::false(isset($list[2]));
		Assert::null($list[2] ?? null);
		Assert::same(2, count($list));
		Assert::same([$a, $b], iterator_to_array($list));
		Assert::exception(fn() => $list[2], OutOfRangeException::class, 'Index 2 is out of range, the list has 2 items.');
		Assert::exception(fn() => $list[0] = node('x'), LogicException::class, 'A list is changed by its methods, `append()`, `insert()`, `removeItem()` and `replaceChild()`.');
		Assert::exception(function () use ($list) { unset($list[0]); }, LogicException::class, 'A list is changed by its methods, `append()`, `insert()`, `removeItem()` and `replaceChild()`.');
	}
});


test('a statement inserted into a file stands where its neighbor stands', function () {
	$parser = new Parser;
	$builder = new Builder;

	// the blank line before the class belongs to the class and stays there
	$file = $parser->parse("<?php\nnamespace App;\n\nuse App\\Money;\n\nclass X\n{\n}\n");
	$namespace = $file->find(NamespaceNode::class)[0];
	$namespace->statements->insert(1, $builder->statement('use App\Currency;'));
	Assert::same("<?php\nnamespace App;\n\nuse App\\Money;\nuse App\\Currency;\n\nclass X\n{\n}\n", (string) $file);

	// a member takes the indentation of the one above it
	$file = $parser->parse("<?php\nclass A\n{\n\tpublic function a() {}\n}\n");
	$file->find(ClassNode::class)[0]->members->append($builder->fragment(MemberNode::class, 'public function b() {}'));
	Assert::same("<?php\nclass A\n{\n\tpublic function a() {}\n\tpublic function b() {}\n}\n", (string) $file);

	// in a list written on one line the neighbor ends with a space, so the new item does too
	$file = $parser->parse('<?php function f() { a(); b(); }');
	$file->find(BlockNode::class)[0]->statements->append($builder->statement('c();'));
	Assert::same('<?php function f() { a(); b(); c(); }', (string) $file);

	// what already ends its line inside its own text is given no line ending on top of it
	$file = $parser->parse("<?php\n\$a = 1;\n");
	$closing = $builder->statement('?' . '>');
	$closing->getLastToken()->setText("?>\n"); // a close tag keeps the line ending PHP swallows after it
	$file->statements->append($closing);
	Assert::same("<?php\n\$a = 1;\n?>\n", (string) $file);

	// an item that carries trivia of its own is taken as it is
	$file = $parser->parse("<?php\n\$a = 1;\n");
	$copy = clone $file->statements[0];
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
	$modifiers = new ModifiersNode([$public = new Token(Token::Public, 'public')]);
	Assert::true($modifiers->has(Token::Public));
	Assert::false($modifiers->has(Token::Static));
	$modifiers->append($static = new Token(Token::Static, 'static'));
	Assert::same('publicstatic ', (string) $modifiers);
	$modifiers->removeToken($public);
	Assert::same([$static], $modifiers->getTokens());
	Assert::null($public->parent);
});


test('a modifier appended or removed keeps the trivia of the declaration where they belong', function () {
	$parser = new Parser;
	$builder = new Builder;
	$change = function (string $code, callable $edit) use ($parser): string {
		$file = $parser->parse($code);
		$all = $file->find(ModifiersNode::class);
		$edit($all[count($all) - 1]); // those of the method where the class has some too
		return (string) $file;
	};
	$final = fn() => new Token(Token::Final, 'final');

	// the first modifier opens the declaration, the open tag and the doc comment go before it
	Assert::same("<?php\n\n/** doc */\nfinal class B {}\n", $change("<?php\n\n/** doc */\nclass B {}\n", fn($m) => $m->append($final())));
	Assert::same("<?php\nclass A\n{\n\tfinal function g() {}\n}\n", $change("<?php\nclass A\n{\n\tfunction g() {}\n}\n", fn($m) => $m->append($final())));
	Assert::same("<?php\nreadonly final class B {}\n", $change("<?php\nreadonly class B {}\n", fn($m) => $m->append($final())));

	// the one after a removed modifier takes its place in the lines
	$remove = fn(int $kind) => fn(ModifiersNode $m) => $m->removeToken($m->findToken($kind) ?? throw new LogicException);
	Assert::same("<?php\n\n/** doc */\nclass E {}\n", $change("<?php\n\n/** doc */\nreadonly class E {}\n", $remove(Token::Readonly)));
	Assert::same("<?php\nclass A\n{\n\t/** doc */\n\tstatic function f() {}\n}\n", $change("<?php\nclass A\n{\n\t/** doc */\n\tpublic static function f() {}\n}\n", $remove(Token::Public)));
	Assert::same("<?php\nclass A\n{\n\tpublic function f() {}\n}\n", $change("<?php\nclass A\n{\n\tpublic static function f() {}\n}\n", $remove(Token::Static)));

	// a comment after the removed modifier stays
	Assert::same("<?php\nclass A\n{\n\tpublic /* x */ function f() {}\n}\n", $change("<?php\nclass A\n{\n\tpublic static /* x */ function f() {}\n}\n", $remove(Token::Static)));
	Assert::same("<?php\nclass A\n{\n\t/* x */ static function f() {}\n}\n", $change("<?php\nclass A\n{\n\tpublic /* x */ static function f() {}\n}\n", $remove(Token::Public)));
});


test('a line a removed modifier ended after another token ends with that token', function () {
	$parser = new Parser;
	$builder = new Builder;
	foreach ([
		"<?php\nabstract class A{abstract\nfunction f();}\n" => ["<?php\nabstract class A{\nfunction f();}\n", ['LineEnding']],
		"<?php\nabstract class A{abstract // x\nfunction f();}\n" => ["<?php\nabstract class A{ // x\nfunction f();}\n", ['Whitespace', 'Comment', 'LineEnding']],
	] as $code => [$expected, $trailing]) {
		$file = $parser->parse($code);
		$method = $file->find(MethodNode::class)[0];
		$method->modifiers->removeToken($method->modifiers->getTokens()[0]);
		Assert::same($expected, (string) $file);
		// where the lexer puts the line ending: in the trailing trivia of the brace, not before the function
		$brace = $method->functionKeyword->getPrevious() ?? throw new LogicException;
		Assert::same($trailing, array_map(fn(Trivia $trivia) => Dumper::findKindName($trivia), $brace->trailingTrivia));
		Assert::same([], $method->functionKeyword->leadingTrivia);
	}
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
	return new StubStatement(new Token(Token::Identifier, $text));
}


test('FileNode counts mutations', function () {
	$stmt = stmt('a');
	$file = new FileNode(new PlainNodeList([$stmt]), $eof = new Token(Token::EndOfFile, ''));
	Assert::same($file, $stmt->getFile());
	Assert::same($file, $file->statements->parent);
	Assert::same(0, $file->revision);
	$file->statements->append(stmt('b'));
	Assert::same(1, $file->revision);
	$file->endOfFile = new Token(Token::EndOfFile, '');
	Assert::same(2, $file->revision);
	Assert::null($eof->parent);
	Assert::null(node('x')->getFile());
});
