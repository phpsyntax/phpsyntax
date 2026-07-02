<?php declare(strict_types=1);

// types: named, nullable, union, intersection, DNF, static

use PhpSyntax\Parser;
use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';

$input = <<<'XX'
	<?php
	function f(int $a, ?B $b, A|B|null $c, A&B $d, (A&B)|null $e, array $f, callable $g, \Foo\Bar $h, self $i): static { }
	function g(): ?int { }
	function h(): A|(B&C) { }
	class X { public ?A $a; protected A|B $b; private (A&B)|C $c; const int D = 1; }
	XX;

$file = (new Parser)->parse($input);
Assert::same($input, (string) $file);
Assert::same(loadExpected(__FILE__, __COMPILER_HALT_OFFSET__), Dumper::dump($file));

__halt_compiler();
FileNode
  statements: NodeList
    - FunctionNode
      attributes: NodeList
      functionKeyword: Function "function"  <OpenTag"<?php\n"  >Whitespace" "
      name: IdentifierNode
        token: Identifier "f"
      openParen: '(' "("
      parameters: SeparatedNodeList
        - ParameterNode
          attributes: NodeList
          modifiers: ModifiersNode
          type: NamedTypeNode
            name: NameNode
              token: Identifier "int"  >Whitespace" "
          variable: VariableNode
            name: Variable "$a"
        - ',' ","  >Whitespace" "
        - ParameterNode
          attributes: NodeList
          modifiers: ModifiersNode
          type: NullableTypeNode
            question: '?' "?"
            type: NamedTypeNode
              name: NameNode
                token: Identifier "B"  >Whitespace" "
          variable: VariableNode
            name: Variable "$b"
        - ',' ","  >Whitespace" "
        - ParameterNode
          attributes: NodeList
          modifiers: ModifiersNode
          type: UnionTypeNode
            types: SeparatedNodeList
              - NamedTypeNode
                name: NameNode
                  token: Identifier "A"
              - '|' "|"
              - NamedTypeNode
                name: NameNode
                  token: Identifier "B"
              - '|' "|"
              - NamedTypeNode
                name: NameNode
                  token: Identifier "null"  >Whitespace" "
          variable: VariableNode
            name: Variable "$c"
        - ',' ","  >Whitespace" "
        - ParameterNode
          attributes: NodeList
          modifiers: ModifiersNode
          type: IntersectionTypeNode
            types: SeparatedNodeList
              - NamedTypeNode
                name: NameNode
                  token: Identifier "A"
              - AmpersandNotFollowedByVariableOrVariadic "&"
              - NamedTypeNode
                name: NameNode
                  token: Identifier "B"  >Whitespace" "
          variable: VariableNode
            name: Variable "$d"
        - ',' ","  >Whitespace" "
        - ParameterNode
          attributes: NodeList
          modifiers: ModifiersNode
          type: UnionTypeNode
            types: SeparatedNodeList
              - IntersectionTypeNode
                openParen: '(' "("
                types: SeparatedNodeList
                  - NamedTypeNode
                    name: NameNode
                      token: Identifier "A"
                  - AmpersandNotFollowedByVariableOrVariadic "&"
                  - NamedTypeNode
                    name: NameNode
                      token: Identifier "B"
                closeParen: ')' ")"
              - '|' "|"
              - NamedTypeNode
                name: NameNode
                  token: Identifier "null"  >Whitespace" "
          variable: VariableNode
            name: Variable "$e"
        - ',' ","  >Whitespace" "
        - ParameterNode
          attributes: NodeList
          modifiers: ModifiersNode
          type: NamedTypeNode
            name: NameNode
              token: Array "array"  >Whitespace" "
          variable: VariableNode
            name: Variable "$f"
        - ',' ","  >Whitespace" "
        - ParameterNode
          attributes: NodeList
          modifiers: ModifiersNode
          type: NamedTypeNode
            name: NameNode
              token: Callable "callable"  >Whitespace" "
          variable: VariableNode
            name: Variable "$g"
        - ',' ","  >Whitespace" "
        - ParameterNode
          attributes: NodeList
          modifiers: ModifiersNode
          type: NamedTypeNode
            name: NameNode
              token: NameFullyQualified "\\Foo\\Bar"  >Whitespace" "
          variable: VariableNode
            name: Variable "$h"
        - ',' ","  >Whitespace" "
        - ParameterNode
          attributes: NodeList
          modifiers: ModifiersNode
          type: NamedTypeNode
            name: NameNode
              token: Identifier "self"  >Whitespace" "
          variable: VariableNode
            name: Variable "$i"
      closeParen: ')' ")"
      colon: ':' ":"  >Whitespace" "
      returnType: NamedTypeNode
        name: NameNode
          token: Static "static"  >Whitespace" "
      body: BlockNode
        openBrace: '{' "{"  >Whitespace" "
        statements: NodeList
        closeBrace: '}' "}"  >EndOfLine"\n"
    - FunctionNode
      attributes: NodeList
      functionKeyword: Function "function"  >Whitespace" "
      name: IdentifierNode
        token: Identifier "g"
      openParen: '(' "("
      parameters: SeparatedNodeList
      closeParen: ')' ")"
      colon: ':' ":"  >Whitespace" "
      returnType: NullableTypeNode
        question: '?' "?"
        type: NamedTypeNode
          name: NameNode
            token: Identifier "int"  >Whitespace" "
      body: BlockNode
        openBrace: '{' "{"  >Whitespace" "
        statements: NodeList
        closeBrace: '}' "}"  >EndOfLine"\n"
    - FunctionNode
      attributes: NodeList
      functionKeyword: Function "function"  >Whitespace" "
      name: IdentifierNode
        token: Identifier "h"
      openParen: '(' "("
      parameters: SeparatedNodeList
      closeParen: ')' ")"
      colon: ':' ":"  >Whitespace" "
      returnType: UnionTypeNode
        types: SeparatedNodeList
          - NamedTypeNode
            name: NameNode
              token: Identifier "A"
          - '|' "|"
          - IntersectionTypeNode
            openParen: '(' "("
            types: SeparatedNodeList
              - NamedTypeNode
                name: NameNode
                  token: Identifier "B"
              - AmpersandNotFollowedByVariableOrVariadic "&"
              - NamedTypeNode
                name: NameNode
                  token: Identifier "C"
            closeParen: ')' ")"  >Whitespace" "
      body: BlockNode
        openBrace: '{' "{"  >Whitespace" "
        statements: NodeList
        closeBrace: '}' "}"  >EndOfLine"\n"
    - ClassNode
      attributes: NodeList
      modifiers: ModifiersNode
      classKeyword: ClassKeyword "class"  >Whitespace" "
      name: IdentifierNode
        token: Identifier "X"  >Whitespace" "
      openBrace: '{' "{"  >Whitespace" "
      members: NodeList
        - PropertyNode
          attributes: NodeList
          modifiers: ModifiersNode
            - Public "public"  >Whitespace" "
          type: NullableTypeNode
            question: '?' "?"
            type: NamedTypeNode
              name: NameNode
                token: Identifier "A"  >Whitespace" "
          items: SeparatedNodeList
            - PropertyItemNode
              name: Variable "$a"
          semicolon: ';' ";"  >Whitespace" "
        - PropertyNode
          attributes: NodeList
          modifiers: ModifiersNode
            - Protected "protected"  >Whitespace" "
          type: UnionTypeNode
            types: SeparatedNodeList
              - NamedTypeNode
                name: NameNode
                  token: Identifier "A"
              - '|' "|"
              - NamedTypeNode
                name: NameNode
                  token: Identifier "B"  >Whitespace" "
          items: SeparatedNodeList
            - PropertyItemNode
              name: Variable "$b"
          semicolon: ';' ";"  >Whitespace" "
        - PropertyNode
          attributes: NodeList
          modifiers: ModifiersNode
            - Private "private"  >Whitespace" "
          type: UnionTypeNode
            types: SeparatedNodeList
              - IntersectionTypeNode
                openParen: '(' "("
                types: SeparatedNodeList
                  - NamedTypeNode
                    name: NameNode
                      token: Identifier "A"
                  - AmpersandNotFollowedByVariableOrVariadic "&"
                  - NamedTypeNode
                    name: NameNode
                      token: Identifier "B"
                closeParen: ')' ")"
              - '|' "|"
              - NamedTypeNode
                name: NameNode
                  token: Identifier "C"  >Whitespace" "
          items: SeparatedNodeList
            - PropertyItemNode
              name: Variable "$c"
          semicolon: ';' ";"  >Whitespace" "
        - ClassConstNode
          attributes: NodeList
          modifiers: ModifiersNode
          constKeyword: Const "const"  >Whitespace" "
          type: NamedTypeNode
            name: NameNode
              token: Identifier "int"  >Whitespace" "
          items: SeparatedNodeList
            - ConstItemNode
              name: IdentifierNode
                token: Identifier "D"  >Whitespace" "
              equals: '=' "="  >Whitespace" "
              value: IntegerNode
                token: Integer "1"
          semicolon: ';' ";"  >Whitespace" "
      closeBrace: '}' "}"
  endOfFile: EndOfFile ""
