<?php declare(strict_types=1);

use PhpSyntax\{Builder, Node, Parser, Token, Trivia};
use PhpSyntax\Nodes\Expression\{ArrayNode, FunctionCallNode};
use PhpSyntax\Nodes\{FileNode, MemberNode, ModifiersNode, NodeList, PlainNodeList, SeparatedNodeList, StatementNode};
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Statement\{BlockNode, ClassNode, FunctionNode, NamespaceNode};
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

	Assert::exception(fn() => $list->append($x), LogicException::class, 'The node already belongs to a tree; a copy comes from `withoutEdgeTrivia()`, or from `clone` with the trivia on its edges.');
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


test('an item is inserted next to another one, and its siblings are its neighbors in the list', function () {
	$parser = new Parser;
	$builder = new Builder;
	$file = $parser->parse("<?php\n\$a;\n\$c;\nf(1, 3);\n");
	[$a, $c] = $file->statements->getItems();
	$file->statements->insertAfter($a, $builder->statement('$b;'));
	$file->statements->insertBefore($c, $builder->statement('$z;'));
	Assert::same("<?php\n\$a;\n\$b;\n\$z;\n\$c;\nf(1, 3);\n", (string) $file);

	$arguments = ($file->findFirst(FunctionCallNode::class) ?? throw new LogicException)->arguments->items;
	$arguments->insertAfter($arguments[0], $builder->fragment(PhpSyntax\Nodes\ArgumentNode::class, '2'));
	$arguments->insertBefore($arguments[0], $builder->fragment(PhpSyntax\Nodes\ArgumentNode::class, '0'), new Token(ord(','), ',')->setTrailingTrivia([new Trivia(Trivia::Whitespace, '  ')]));
	Assert::same("<?php\n\$a;\n\$b;\n\$z;\n\$c;\nf(0,  1, 2, 3);\n", (string) $file);

	Assert::same('$b;', $a->getNextSibling()?->text);
	Assert::same('$z;', $c->getPreviousSibling()?->text);
	Assert::null($file->statements[0]->getPreviousSibling());
	Assert::null($arguments[3]->getNextSibling());
	Assert::same('2', $arguments[3]->getPreviousSibling()?->text);
	Assert::exception(
		($arguments[0]->getFirstToken()->parent ?? throw new LogicException)->getNextSibling(...),
		LogicException::class,
		'Only an item of a list has siblings; a slot holds no other node.',
	);
});


test('a block standing among statements is unwrapped into them', function () {
	$parser = new Parser;
	$builder = new Builder;
	$file = $parser->parse("<?php\n\$a;\n{ // start\n\t\$b;\n\t\$c;\n}\n\$d;\n");
	$block = $file->findFirst(BlockNode::class) ?? throw new LogicException;
	$block->unwrap();
	Assert::same("<?php\n\$a;\n// start\n\t\$b;\n\t\$c;\n\$d;\n", (string) $file);
	Assert::same(['$a;', '$b;', '$c;', '$d;'], array_map(fn(Node $statement) => $statement->text, $file->statements->getItems()));

	$file = $parser->parse("<?php\n{}\n\$a;\n");
	($file->findFirst(BlockNode::class) ?? throw new LogicException)->unwrap();
	Assert::same("<?php\n\$a;\n", (string) $file);

	// a comment on the closing brace goes after the last statement, on a line of its own as remove() puts it
	$file = $parser->parse("<?php\n\$a;\n{\n\t\$b;\n\t\$c;\n\t// tail\n} // end\n\$d;\n");
	($file->findFirst(BlockNode::class) ?? throw new LogicException)->unwrap();
	Assert::same("<?php\n\$a;\n\t\$b;\n\t\$c;\n\t// tail\n// end\n\$d;\n", (string) $file);
	Assert::same([3, 4, 7], array_map(fn(StatementNode $statement) => $statement->getFirstToken()->currentLine, array_slice($file->statements->getItems(), 1)));

	$file = $parser->parse("<?php\n{\n\t\$b;\n} /* end */ \$d;\n");
	($file->findFirst(BlockNode::class) ?? throw new LogicException)->unwrap();
	Assert::same("<?php\n\t\$b;\n/* end */ \$d;\n", (string) $file);

	$file = $parser->parse("<?php\n{\n\t\$a;\n} // c");
	($file->findFirst(BlockNode::class) ?? throw new LogicException)->unwrap();
	Assert::same("<?php\n\t\$a;\n// c", (string) $file);

	// one before the opening brace stays before the first statement, on its line or on a line of its own
	$file = $parser->parse("<?php\n/* lead */ {\n\t\$b;\n}\n");
	($file->findFirst(BlockNode::class) ?? throw new LogicException)->unwrap();
	Assert::same("<?php\n/* lead */\n\t\$b;\n", (string) $file);

	$file = $parser->parse('<?php { /* lead */ $b; }');
	($file->findFirst(BlockNode::class) ?? throw new LogicException)->unwrap();
	Assert::same('<?php /* lead */ $b; ', (string) $file);

	// next to a close tag or inline HTML the whitespace is output, so the block is refused before anything moves
	foreach (["<?php {\n\techo 2 ?>\nhtml2\n<?php }", "<?php {\n\t\$b;\n} ?>\nx", "x<?php {\n\t\$b;\n}"] as $code) {
		$file = $parser->parse($code);
		Assert::exception(
			($file->findFirst(BlockNode::class) ?? throw new LogicException)->unwrap(...),
			LogicException::class,
			'The block stands next to a close tag or inline HTML, where the whitespace its braces leave would be output of the script.',
		);
		Assert::same($code, (string) $file);
	}

	$file = $parser->parse("<?php\nif (\$a) {\n\t\$b;\n}\n");
	Assert::exception(
		($file->findFirst(BlockNode::class) ?? throw new LogicException)->unwrap(...),
		LogicException::class,
		'Only a block standing among statements can be unwrapped; a body is written by its setter.',
	);
});


test('the trailing separator stands where the last item ended', function () {
	$parser = new Parser;
	$builder = new Builder;
	$file = $parser->parse("<?php\n\$a = [\n\t1,\n\t2 // two\n];\nf(1, 2);\n");
	$items = ($file->findFirst(ArrayNode::class) ?? throw new LogicException)->items;
	Assert::null($items->getTrailingSeparator());
	$items->setTrailingSeparator($comma = new Token(ord(','), ','));
	Assert::same("<?php\n\$a = [\n\t1,\n\t2, // two\n];\nf(1, 2);\n", (string) $file);
	Assert::same($comma, $items->getTrailingSeparator());

	// removed, it leaves its trailing trivia to the last item
	$items->setTrailingSeparator(null);
	Assert::same("<?php\n\$a = [\n\t1,\n\t2 // two\n];\nf(1, 2);\n", (string) $file);

	// one replacing another takes its trivia, and one with trivia of its own is written as it is
	$items->setTrailingSeparator(new Token(ord(','), ','));
	$items->setTrailingSeparator(new Token(ord(','), ','));
	Assert::same("<?php\n\$a = [\n\t1,\n\t2, // two\n];\nf(1, 2);\n", (string) $file);
	$arguments = ($file->findFirst(FunctionCallNode::class) ?? throw new LogicException)->arguments->items;
	$arguments->setTrailingSeparator(new Token(ord(','), ',')->setTrailingTrivia([new Trivia(Trivia::Whitespace, ' ')]));
	Assert::same("<?php\n\$a = [\n\t1,\n\t2, // two\n];\nf(1, 2, );\n", (string) $file);

	// a doc comment right after the last item documents a parameter there, so it stays before the separator
	$file = $parser->parse("<?php\nfunction f(\n\tint \$a /** the a */ // note\n) {}\n");
	$parameters = ($file->findFirst(FunctionNode::class) ?? throw new LogicException)->parameters;
	$parameters->setTrailingSeparator(Token::fromText(','));
	Assert::same("<?php\nfunction f(\n\tint \$a /** the a */, // note\n) {}\n", (string) $file);
	$parameters->setTrailingSeparator(null);
	Assert::same("<?php\nfunction f(\n\tint \$a /** the a */ // note\n) {}\n", (string) $file);

	// and so does one on the line before the closing parenthesis
	$file = $parser->parse("<?php\nfunction f(\n\t\$a\n\t/** d */ // note\n\t// other\n) {}\n");
	$parameters = ($file->findFirst(FunctionNode::class) ?? throw new LogicException)->parameters;
	$parameters->setTrailingSeparator(Token::fromText(','));
	Assert::same("<?php\nfunction f(\n\t\$a\n\t/** d */, // note\n\t// other\n) {}\n", (string) $file);
	Assert::same([' ', '// note', "\n"], array_map(fn(Trivia $trivia) => $trivia->text, $parameters->getTrailingSeparator()->trailingTrivia ?? []));
	Assert::same('/** d */', $parameters[0]->getDocComment()?->text);
});


test('the comments of a trailing separator stay when it is removed or replaced', function () {
	$parser = new Parser;
	$items = fn(FileNode $file) => ($file->findFirst(ArrayNode::class) ?? throw new LogicException)->items;

	$file = $parser->parse("<?php\n\$a = [\n\t1,\n\t2\n\t/* c */,\n];\n");
	$items($file)->setTrailingSeparator(null);
	Assert::same("<?php\n\$a = [\n\t1,\n\t2\n\t/* c */\n];\n", (string) $file);

	$file = $parser->parse("<?php\n\$a = [\n\t1,\n\t2\n\t,\n];\n");
	$items($file)->setTrailingSeparator(null);
	Assert::same("<?php\n\$a = [\n\t1,\n\t2\n];\n", (string) $file);

	$file = $parser->parse("<?php\n\$a = [\n\t1,\n\t2, // two\n];\n");
	$items($file)->setTrailingSeparator(new Token(ord(','), ',')->setTrailingTrivia([new Trivia(Trivia::Whitespace, ' ')]));
	Assert::same("<?php\n\$a = [\n\t1,\n\t2, // two\n];\n", (string) $file);

	$file = $parser->parse("<?php\n\$a = [\n\t1,\n\t2\n\t/* c */,\n];\n");
	$items($file)->setTrailingSeparator(new Token(ord(','), ',')->setTrailingTrivia([new Trivia(Trivia::Whitespace, ' ')]));
	Assert::same("<?php\n\$a = [\n\t1,\n\t2\n\t/* c */,\n];\n", (string) $file);
});


test('a trailing separator written as it stands changes nothing, nor does one in a list being built', function () {
	$file = new Parser()->parse("<?php\n\$a = [1, 2];\n\$b = [1, 2,];\n");
	[$first, $second] = $file->find(ArrayNode::class);
	$revision = $file->revision;
	$first->items->setTrailingSeparator(null);
	$second->items->setTrailingSeparator($second->items->getTrailingSeparator());
	Assert::same($revision, $file->revision);

	// the parser builds a list of tokens that carry their trivia already
	$list = new SeparatedNodeList([new StubNode(new Token(Token::Identifier, 'a')->setTrailingTrivia([new Trivia(Trivia::Whitespace, ' ')]))]);
	$list->setTrailingSeparator($comma = comma());
	Assert::same('a ,', (string) $list);
	Assert::same([], $comma->trailingTrivia);
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
