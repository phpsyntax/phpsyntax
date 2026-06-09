<?php declare(strict_types=1);

// attributes on every construct that accepts them

use PhpSyntax\Parser;
use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';

$input = <<<'XX'
	<?php
	#[A, B(1, name: 2),] #[C]
	class X {
		#[A] const B = 1;
		#[A] public $c;
		#[A] public function m(#[A] $p) { }
	}
	#[A] function f(#[A] int $p) { }
	#[A] enum E { #[A] case C; }
	#[A] interface I { }
	#[A] trait T { }
	$c = new #[A] class { };
	$f = #[A] function () { };
	#[A] const D = 1;
	XX;

$file = (new Parser)->parse($input);
Assert::same($input, (string) $file);
Assert::same(loadExpected(__FILE__, __COMPILER_HALT_OFFSET__), Dumper::dump($file));

__halt_compiler();
FileNode
	statements: PlainNodeList
		- ClassNode
			attributes: PlainNodeList
				- AttributeGroupNode
					openBracket: Attribute "#["  <OpenTag"<?php\n"
					items: SeparatedNodeList
						- AttributeNode
							name: NameNode
								token: Identifier "A"
						- ',' ","  >Whitespace" "
						- AttributeNode
							name: NameNode
								token: Identifier "B"
							arguments: ArgumentListNode
								openParen: '(' "("
								items: SeparatedNodeList
									- ArgumentNode
										value: IntegerNode
											token: Integer "1"
									- ',' ","  >Whitespace" "
									- ArgumentNode
										name: IdentifierNode
											token: Identifier "name"
										colon: ':' ":"  >Whitespace" "
										value: IntegerNode
											token: Integer "2"
								closeParen: ')' ")"
						- ',' ","
					closeBracket: ']' "]"  >Whitespace" "
				- AttributeGroupNode
					openBracket: Attribute "#["
					items: SeparatedNodeList
						- AttributeNode
							name: NameNode
								token: Identifier "C"
					closeBracket: ']' "]"  >LineEnding"\n"
			modifiers: ModifiersNode
			classKeyword: ClassKeyword "class"  >Whitespace" "
			name: IdentifierNode
				token: Identifier "X"  >Whitespace" "
			openBrace: '{' "{"  >LineEnding"\n"
			members: PlainNodeList
				- ClassConstNode
					attributes: PlainNodeList
						- AttributeGroupNode
							openBracket: Attribute "#["  <Whitespace"\t"
							items: SeparatedNodeList
								- AttributeNode
									name: NameNode
										token: Identifier "A"
							closeBracket: ']' "]"  >Whitespace" "
					modifiers: ModifiersNode
					constKeyword: Const "const"  >Whitespace" "
					items: SeparatedNodeList
						- ConstItemNode
							name: IdentifierNode
								token: Identifier "B"  >Whitespace" "
							equals: '=' "="  >Whitespace" "
							value: IntegerNode
								token: Integer "1"
					semicolon: ';' ";"  >LineEnding"\n"
				- PropertyNode
					attributes: PlainNodeList
						- AttributeGroupNode
							openBracket: Attribute "#["  <Whitespace"\t"
							items: SeparatedNodeList
								- AttributeNode
									name: NameNode
										token: Identifier "A"
							closeBracket: ']' "]"  >Whitespace" "
					modifiers: ModifiersNode
						- Public "public"  >Whitespace" "
					items: SeparatedNodeList
						- PropertyItemNode
							name: Variable "$c"
					semicolon: ';' ";"  >LineEnding"\n"
				- MethodNode
					attributes: PlainNodeList
						- AttributeGroupNode
							openBracket: Attribute "#["  <Whitespace"\t"
							items: SeparatedNodeList
								- AttributeNode
									name: NameNode
										token: Identifier "A"
							closeBracket: ']' "]"  >Whitespace" "
					modifiers: ModifiersNode
						- Public "public"  >Whitespace" "
					functionKeyword: Function "function"  >Whitespace" "
					name: IdentifierNode
						token: Identifier "m"
					openParen: '(' "("
					parameters: SeparatedNodeList
						- ParameterNode
							attributes: PlainNodeList
								- AttributeGroupNode
									openBracket: Attribute "#["
									items: SeparatedNodeList
										- AttributeNode
											name: NameNode
												token: Identifier "A"
									closeBracket: ']' "]"  >Whitespace" "
							modifiers: ModifiersNode
							variable: VariableNode
								name: Variable "$p"
					closeParen: ')' ")"  >Whitespace" "
					body: BlockNode
						openBrace: '{' "{"  >Whitespace" "
						statements: PlainNodeList
						closeBrace: '}' "}"  >LineEnding"\n"
			closeBrace: '}' "}"  >LineEnding"\n"
		- FunctionNode
			attributes: PlainNodeList
				- AttributeGroupNode
					openBracket: Attribute "#["
					items: SeparatedNodeList
						- AttributeNode
							name: NameNode
								token: Identifier "A"
					closeBracket: ']' "]"  >Whitespace" "
			functionKeyword: Function "function"  >Whitespace" "
			name: IdentifierNode
				token: Identifier "f"
			openParen: '(' "("
			parameters: SeparatedNodeList
				- ParameterNode
					attributes: PlainNodeList
						- AttributeGroupNode
							openBracket: Attribute "#["
							items: SeparatedNodeList
								- AttributeNode
									name: NameNode
										token: Identifier "A"
							closeBracket: ']' "]"  >Whitespace" "
					modifiers: ModifiersNode
					type: NamedTypeNode
						name: NameNode
							token: Identifier "int"  >Whitespace" "
					variable: VariableNode
						name: Variable "$p"
			closeParen: ')' ")"  >Whitespace" "
			body: BlockNode
				openBrace: '{' "{"  >Whitespace" "
				statements: PlainNodeList
				closeBrace: '}' "}"  >LineEnding"\n"
		- EnumNode
			attributes: PlainNodeList
				- AttributeGroupNode
					openBracket: Attribute "#["
					items: SeparatedNodeList
						- AttributeNode
							name: NameNode
								token: Identifier "A"
					closeBracket: ']' "]"  >Whitespace" "
			enumKeyword: Enum "enum"  >Whitespace" "
			name: IdentifierNode
				token: Identifier "E"  >Whitespace" "
			openBrace: '{' "{"  >Whitespace" "
			members: PlainNodeList
				- EnumCaseNode
					attributes: PlainNodeList
						- AttributeGroupNode
							openBracket: Attribute "#["
							items: SeparatedNodeList
								- AttributeNode
									name: NameNode
										token: Identifier "A"
							closeBracket: ']' "]"  >Whitespace" "
					caseKeyword: Case "case"  >Whitespace" "
					name: IdentifierNode
						token: Identifier "C"
					semicolon: ';' ";"  >Whitespace" "
			closeBrace: '}' "}"  >LineEnding"\n"
		- InterfaceNode
			attributes: PlainNodeList
				- AttributeGroupNode
					openBracket: Attribute "#["
					items: SeparatedNodeList
						- AttributeNode
							name: NameNode
								token: Identifier "A"
					closeBracket: ']' "]"  >Whitespace" "
			interfaceKeyword: Interface "interface"  >Whitespace" "
			name: IdentifierNode
				token: Identifier "I"  >Whitespace" "
			openBrace: '{' "{"  >Whitespace" "
			members: PlainNodeList
			closeBrace: '}' "}"  >LineEnding"\n"
		- TraitNode
			attributes: PlainNodeList
				- AttributeGroupNode
					openBracket: Attribute "#["
					items: SeparatedNodeList
						- AttributeNode
							name: NameNode
								token: Identifier "A"
					closeBracket: ']' "]"  >Whitespace" "
			traitKeyword: Trait "trait"  >Whitespace" "
			name: IdentifierNode
				token: Identifier "T"  >Whitespace" "
			openBrace: '{' "{"  >Whitespace" "
			members: PlainNodeList
			closeBrace: '}' "}"  >LineEnding"\n"
		- ExpressionStatementNode
			expression: AssignmentNode
				target: VariableNode
					name: Variable "$c"  >Whitespace" "
				equals: '=' "="  >Whitespace" "
				expression: NewNode
					newKeyword: New "new"  >Whitespace" "
					class: AnonymousClassNode
						attributes: PlainNodeList
							- AttributeGroupNode
								openBracket: Attribute "#["
								items: SeparatedNodeList
									- AttributeNode
										name: NameNode
											token: Identifier "A"
								closeBracket: ']' "]"  >Whitespace" "
						modifiers: ModifiersNode
						classKeyword: ClassKeyword "class"  >Whitespace" "
						openBrace: '{' "{"  >Whitespace" "
						members: PlainNodeList
						closeBrace: '}' "}"
			semicolon: ';' ";"  >LineEnding"\n"
		- ExpressionStatementNode
			expression: AssignmentNode
				target: VariableNode
					name: Variable "$f"  >Whitespace" "
				equals: '=' "="  >Whitespace" "
				expression: ClosureNode
					attributes: PlainNodeList
						- AttributeGroupNode
							openBracket: Attribute "#["
							items: SeparatedNodeList
								- AttributeNode
									name: NameNode
										token: Identifier "A"
							closeBracket: ']' "]"  >Whitespace" "
					functionKeyword: Function "function"  >Whitespace" "
					openParen: '(' "("
					parameters: SeparatedNodeList
					closeParen: ')' ")"  >Whitespace" "
					body: BlockNode
						openBrace: '{' "{"  >Whitespace" "
						statements: PlainNodeList
						closeBrace: '}' "}"
			semicolon: ';' ";"  >LineEnding"\n"
		- ConstNode
			attributes: PlainNodeList
				- AttributeGroupNode
					openBracket: Attribute "#["
					items: SeparatedNodeList
						- AttributeNode
							name: NameNode
								token: Identifier "A"
					closeBracket: ']' "]"  >Whitespace" "
			constKeyword: Const "const"  >Whitespace" "
			items: SeparatedNodeList
				- ConstItemNode
					name: IdentifierNode
						token: Identifier "D"  >Whitespace" "
					equals: '=' "="  >Whitespace" "
					value: IntegerNode
						token: Integer "1"
			semicolon: ';' ";"
	endOfFile: EndOfFile ""
