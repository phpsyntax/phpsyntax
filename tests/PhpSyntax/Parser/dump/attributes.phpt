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
  statements: NodeList
    - ClassNode
      attributes: NodeList
        - AttributeGroupNode
          openAttribute: Attribute "#["  <OpenTag"<?php\n"
          attributes: SeparatedNodeList
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
          openAttribute: Attribute "#["
          attributes: SeparatedNodeList
            - AttributeNode
              name: NameNode
                token: Identifier "C"
          closeBracket: ']' "]"  >EndOfLine"\n"
      modifiers: ModifiersNode
      classKeyword: ClassKeyword "class"  >Whitespace" "
      name: IdentifierNode
        token: Identifier "X"  >Whitespace" "
      openBrace: '{' "{"  >EndOfLine"\n"
      members: NodeList
        - ClassConstNode
          attributes: NodeList
            - AttributeGroupNode
              openAttribute: Attribute "#["  <Whitespace"\t"
              attributes: SeparatedNodeList
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
          semicolon: ';' ";"  >EndOfLine"\n"
        - PropertyNode
          attributes: NodeList
            - AttributeGroupNode
              openAttribute: Attribute "#["  <Whitespace"\t"
              attributes: SeparatedNodeList
                - AttributeNode
                  name: NameNode
                    token: Identifier "A"
              closeBracket: ']' "]"  >Whitespace" "
          modifiers: ModifiersNode
            - Public "public"  >Whitespace" "
          items: SeparatedNodeList
            - PropertyItemNode
              name: Variable "$c"
          semicolon: ';' ";"  >EndOfLine"\n"
        - MethodNode
          attributes: NodeList
            - AttributeGroupNode
              openAttribute: Attribute "#["  <Whitespace"\t"
              attributes: SeparatedNodeList
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
              attributes: NodeList
                - AttributeGroupNode
                  openAttribute: Attribute "#["
                  attributes: SeparatedNodeList
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
            statements: NodeList
            closeBrace: '}' "}"  >EndOfLine"\n"
      closeBrace: '}' "}"  >EndOfLine"\n"
    - FunctionNode
      attributes: NodeList
        - AttributeGroupNode
          openAttribute: Attribute "#["
          attributes: SeparatedNodeList
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
          attributes: NodeList
            - AttributeGroupNode
              openAttribute: Attribute "#["
              attributes: SeparatedNodeList
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
        statements: NodeList
        closeBrace: '}' "}"  >EndOfLine"\n"
    - EnumNode
      attributes: NodeList
        - AttributeGroupNode
          openAttribute: Attribute "#["
          attributes: SeparatedNodeList
            - AttributeNode
              name: NameNode
                token: Identifier "A"
          closeBracket: ']' "]"  >Whitespace" "
      enumKeyword: Enum "enum"  >Whitespace" "
      name: IdentifierNode
        token: Identifier "E"  >Whitespace" "
      openBrace: '{' "{"  >Whitespace" "
      members: NodeList
        - EnumCaseNode
          attributes: NodeList
            - AttributeGroupNode
              openAttribute: Attribute "#["
              attributes: SeparatedNodeList
                - AttributeNode
                  name: NameNode
                    token: Identifier "A"
              closeBracket: ']' "]"  >Whitespace" "
          caseKeyword: Case "case"  >Whitespace" "
          name: IdentifierNode
            token: Identifier "C"
          semicolon: ';' ";"  >Whitespace" "
      closeBrace: '}' "}"  >EndOfLine"\n"
    - InterfaceNode
      attributes: NodeList
        - AttributeGroupNode
          openAttribute: Attribute "#["
          attributes: SeparatedNodeList
            - AttributeNode
              name: NameNode
                token: Identifier "A"
          closeBracket: ']' "]"  >Whitespace" "
      interfaceKeyword: Interface "interface"  >Whitespace" "
      name: IdentifierNode
        token: Identifier "I"  >Whitespace" "
      openBrace: '{' "{"  >Whitespace" "
      members: NodeList
      closeBrace: '}' "}"  >EndOfLine"\n"
    - TraitNode
      attributes: NodeList
        - AttributeGroupNode
          openAttribute: Attribute "#["
          attributes: SeparatedNodeList
            - AttributeNode
              name: NameNode
                token: Identifier "A"
          closeBracket: ']' "]"  >Whitespace" "
      traitKeyword: Trait "trait"  >Whitespace" "
      name: IdentifierNode
        token: Identifier "T"  >Whitespace" "
      openBrace: '{' "{"  >Whitespace" "
      members: NodeList
      closeBrace: '}' "}"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$c"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: NewNode
          newKeyword: New "new"  >Whitespace" "
          class: AnonymousClassNode
            attributes: NodeList
              - AttributeGroupNode
                openAttribute: Attribute "#["
                attributes: SeparatedNodeList
                  - AttributeNode
                    name: NameNode
                      token: Identifier "A"
                closeBracket: ']' "]"  >Whitespace" "
            modifiers: ModifiersNode
            classKeyword: ClassKeyword "class"  >Whitespace" "
            openBrace: '{' "{"  >Whitespace" "
            members: NodeList
            closeBrace: '}' "}"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$f"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: ClosureNode
          attributes: NodeList
            - AttributeGroupNode
              openAttribute: Attribute "#["
              attributes: SeparatedNodeList
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
            statements: NodeList
            closeBrace: '}' "}"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ConstNode
      attributes: NodeList
        - AttributeGroupNode
          openAttribute: Attribute "#["
          attributes: SeparatedNodeList
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
