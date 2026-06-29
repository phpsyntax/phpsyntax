<?php declare(strict_types=1);

// functions, parameters, closures and arrow functions

use PhpSyntax\Parser;
use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';

$input = <<<'XX'
	<?php
	function &f(int $a = 1, &$b, #[A] ?B $d = null, ...$c,): int { return 1; }
	function readonly() { } function exit() { } function clone() { }
	$f = function (A $a) use (&$b): void { };
	$g = static function () { };
	$h = fn(int $x): int => $x * 2;
	$i = static fn() => 1;
	$j = #[A] fn() => 1;
	$k = #[A] static function () { };
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
      ampersand: AmpersandNotFollowedByVariableOrVariadic "&"
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
            name: Variable "$a"  >Whitespace" "
          equals: '=' "="  >Whitespace" "
          default: IntegerNode
            token: Integer "1"
        - ',' ","  >Whitespace" "
        - ParameterNode
          attributes: NodeList
          modifiers: ModifiersNode
          ampersand: AmpersandFollowedByVariableOrVariadic "&"
          variable: VariableNode
            name: Variable "$b"
        - ',' ","  >Whitespace" "
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
          type: NullableTypeNode
            question: '?' "?"
            type: NamedTypeNode
              name: NameNode
                token: Identifier "B"  >Whitespace" "
          variable: VariableNode
            name: Variable "$d"  >Whitespace" "
          equals: '=' "="  >Whitespace" "
          default: NullNode
            token: Identifier "null"
        - ',' ","  >Whitespace" "
        - ParameterNode
          attributes: NodeList
          modifiers: ModifiersNode
          ellipsis: Ellipsis "..."
          variable: VariableNode
            name: Variable "$c"
        - ',' ","
      closeParen: ')' ")"
      colon: ':' ":"  >Whitespace" "
      returnType: NamedTypeNode
        name: NameNode
          token: Identifier "int"  >Whitespace" "
      body: BlockNode
        openBrace: '{' "{"  >Whitespace" "
        statements: NodeList
          - ReturnNode
            returnKeyword: Return "return"  >Whitespace" "
            expression: IntegerNode
              token: Integer "1"
            semicolon: ';' ";"  >Whitespace" "
        closeBrace: '}' "}"  >EndOfLine"\n"
    - FunctionNode
      attributes: NodeList
      functionKeyword: Function "function"  >Whitespace" "
      name: IdentifierNode
        token: Readonly "readonly"
      openParen: '(' "("
      parameters: SeparatedNodeList
      closeParen: ')' ")"  >Whitespace" "
      body: BlockNode
        openBrace: '{' "{"  >Whitespace" "
        statements: NodeList
        closeBrace: '}' "}"  >Whitespace" "
    - FunctionNode
      attributes: NodeList
      functionKeyword: Function "function"  >Whitespace" "
      name: IdentifierNode
        token: Exit "exit"
      openParen: '(' "("
      parameters: SeparatedNodeList
      closeParen: ')' ")"  >Whitespace" "
      body: BlockNode
        openBrace: '{' "{"  >Whitespace" "
        statements: NodeList
        closeBrace: '}' "}"  >Whitespace" "
    - FunctionNode
      attributes: NodeList
      functionKeyword: Function "function"  >Whitespace" "
      name: IdentifierNode
        token: Clone "clone"
      openParen: '(' "("
      parameters: SeparatedNodeList
      closeParen: ')' ")"  >Whitespace" "
      body: BlockNode
        openBrace: '{' "{"  >Whitespace" "
        statements: NodeList
        closeBrace: '}' "}"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$f"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: ClosureNode
          attributes: NodeList
          functionKeyword: Function "function"  >Whitespace" "
          openParen: '(' "("
          parameters: SeparatedNodeList
            - ParameterNode
              attributes: NodeList
              modifiers: ModifiersNode
              type: NamedTypeNode
                name: NameNode
                  token: Identifier "A"  >Whitespace" "
              variable: VariableNode
                name: Variable "$a"
          closeParen: ')' ")"  >Whitespace" "
          uses: ClosureUsesNode
            useKeyword: Use "use"  >Whitespace" "
            openParen: '(' "("
            variables: SeparatedNodeList
              - ClosureUseNode
                ampersand: AmpersandFollowedByVariableOrVariadic "&"
                variable: VariableNode
                  name: Variable "$b"
            closeParen: ')' ")"
          colon: ':' ":"  >Whitespace" "
          returnType: NamedTypeNode
            name: NameNode
              token: Identifier "void"  >Whitespace" "
          body: BlockNode
            openBrace: '{' "{"  >Whitespace" "
            statements: NodeList
            closeBrace: '}' "}"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$g"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: ClosureNode
          attributes: NodeList
          staticKeyword: Static "static"  >Whitespace" "
          functionKeyword: Function "function"  >Whitespace" "
          openParen: '(' "("
          parameters: SeparatedNodeList
          closeParen: ')' ")"  >Whitespace" "
          body: BlockNode
            openBrace: '{' "{"  >Whitespace" "
            statements: NodeList
            closeBrace: '}' "}"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$h"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: ArrowFunctionNode
          attributes: NodeList
          fnKeyword: Fn "fn"
          openParen: '(' "("
          parameters: SeparatedNodeList
            - ParameterNode
              attributes: NodeList
              modifiers: ModifiersNode
              type: NamedTypeNode
                name: NameNode
                  token: Identifier "int"  >Whitespace" "
              variable: VariableNode
                name: Variable "$x"
          closeParen: ')' ")"
          colon: ':' ":"  >Whitespace" "
          returnType: NamedTypeNode
            name: NameNode
              token: Identifier "int"  >Whitespace" "
          doubleArrow: DoubleArrow "=>"  >Whitespace" "
          expression: BinaryOpNode
            left: VariableNode
              name: Variable "$x"  >Whitespace" "
            operator: '*' "*"  >Whitespace" "
            right: IntegerNode
              token: Integer "2"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$i"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: ArrowFunctionNode
          attributes: NodeList
          staticKeyword: Static "static"  >Whitespace" "
          fnKeyword: Fn "fn"
          openParen: '(' "("
          parameters: SeparatedNodeList
          closeParen: ')' ")"  >Whitespace" "
          doubleArrow: DoubleArrow "=>"  >Whitespace" "
          expression: IntegerNode
            token: Integer "1"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$j"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: ArrowFunctionNode
          attributes: NodeList
            - AttributeGroupNode
              openAttribute: Attribute "#["
              attributes: SeparatedNodeList
                - AttributeNode
                  name: NameNode
                    token: Identifier "A"
              closeBracket: ']' "]"  >Whitespace" "
          fnKeyword: Fn "fn"
          openParen: '(' "("
          parameters: SeparatedNodeList
          closeParen: ')' ")"  >Whitespace" "
          doubleArrow: DoubleArrow "=>"  >Whitespace" "
          expression: IntegerNode
            token: Integer "1"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$k"  >Whitespace" "
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
          staticKeyword: Static "static"  >Whitespace" "
          functionKeyword: Function "function"  >Whitespace" "
          openParen: '(' "("
          parameters: SeparatedNodeList
          closeParen: ')' ")"  >Whitespace" "
          body: BlockNode
            openBrace: '{' "{"  >Whitespace" "
            statements: NodeList
            closeBrace: '}' "}"
      semicolon: ';' ";"
  endOfFile: EndOfFile ""
