<?php declare(strict_types=1);

use PhpSyntax\Nodes\Expression\{TernaryNode, VariableNode};
use PhpSyntax\Nodes\{ModifiersNode, PlainNodeList, SeparatedNodeList};
use PhpSyntax\{Parser, Token, Trivia};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('nodes with slots, lists and tokens with trivia', function () {
	$a = new Token(Token::Variable, '$a');
	$a->setLeadingTrivia([new Trivia(Trivia::OpenTag, "<?php\n"), new Trivia(Trivia::Whitespace, "\t")]);
	$space = new Trivia(Trivia::Whitespace, ' ');
	$space->inInterpolation = true;
	$a->setTrailingTrivia([$space]);
	$ternary = new TernaryNode(
		new VariableNode(null, null, $a, null),
		new Token(ord('?'), '?'),
		null,
		new Token(ord(':'), ':'),
		new VariableNode(null, null, new Token(Token::Variable, '$b'), null),
	);
	Assert::match(<<<'XX'
		TernaryNode
			condition: VariableNode
				name: Variable "$a"  <OpenTag"<?php\n" Whitespace"\t"  >Whitespace*" "
			question: '?' "?"
			colon: ':' ":"
			else: VariableNode
				name: Variable "$b"

		XX, Dumper::dump($ternary));

	$list = new SeparatedNodeList([new ModifiersNode([new Token(Token::Public, 'public')]), new ModifiersNode], [new Token(ord(','), ',')]);
	Assert::match(<<<'XX'
		SeparatedNodeList
			- ModifiersNode
				- Public "public"
			- ',' ","
			- ModifiersNode

		XX, Dumper::dump($list));
	Assert::same("PlainNodeList\n", Dumper::dump(new PlainNodeList));
});


test('tree from the parser', function () {
	Assert::match(<<<'XX'
		FileNode
			statements: PlainNodeList
				- ExpressionStatementNode
					expression: VariableNode
						name: Variable "$a"  <OpenTag"<?php "
					semicolon: ';' ";"
			endOfFile: EndOfFile ""

		XX, Dumper::dump((new Parser)->parse('<?php $a;')));
});
