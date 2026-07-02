<?php declare(strict_types=1);

// classes, interfaces, traits, enums, members, modifiers, hooks, adaptations, anonymous classes

use PhpSyntax\Parser;
use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';

$input = <<<'XX'
	<?php
	abstract readonly class A extends B implements C, D {
		use T; use U, V { U::m insteadof V; V::m as protected n; m as public; m as o; }
		const X = 1, Y = 2; final public const int Z = 3;
		var $a; public static ?int $b = 1, $c; private(set) readonly string $d { get => $this->d; set($v) { $this->d = $v; } }
		abstract protected function m(): void;
		public static function &n(): static { }
		final function o() { }
	}
	interface I extends J, K { const A = 1; public function m(); }
	trait T { public function m() { } }
	enum E: string implements I { case A = 'a'; case B; const C = self::A; public function m() { } }
	enum F { case A; }
	final class G { public function __construct(public readonly int $a, protected $b = 1, private(set) int $c { get => 1; }) { } }
	$x = new class(1) extends A { };
	$y = new readonly class { };
	XX;

$file = (new Parser)->parse($input);
Assert::same($input, (string) $file);
Assert::same(loadExpected(__FILE__, __COMPILER_HALT_OFFSET__), Dumper::dump($file));

__halt_compiler();
FileNode
  statements: NodeList
    - ClassNode
      attributes: NodeList
      modifiers: ModifiersNode
        - Abstract "abstract"  <OpenTag"<?php\n"  >Whitespace" "
        - Readonly "readonly"  >Whitespace" "
      classKeyword: ClassKeyword "class"  >Whitespace" "
      name: IdentifierNode
        token: Identifier "A"  >Whitespace" "
      extendsKeyword: Extends "extends"  >Whitespace" "
      extends: NameNode
        token: Identifier "B"  >Whitespace" "
      implementsKeyword: Implements "implements"  >Whitespace" "
      implements: SeparatedNodeList
        - NameNode
          token: Identifier "C"
        - ',' ","  >Whitespace" "
        - NameNode
          token: Identifier "D"  >Whitespace" "
      openBrace: '{' "{"  >EndOfLine"\n"
      members: NodeList
        - TraitUseNode
          useKeyword: Use "use"  <Whitespace"\t"  >Whitespace" "
          traits: SeparatedNodeList
            - NameNode
              token: Identifier "T"
          semicolon: ';' ";"  >Whitespace" "
        - TraitUseNode
          useKeyword: Use "use"  >Whitespace" "
          traits: SeparatedNodeList
            - NameNode
              token: Identifier "U"
            - ',' ","  >Whitespace" "
            - NameNode
              token: Identifier "V"  >Whitespace" "
          openBrace: '{' "{"  >Whitespace" "
          adaptations: NodeList
            - TraitPrecedenceNode
              trait: NameNode
                token: Identifier "U"
              doubleColon: DoubleColon "::"
              method: IdentifierNode
                token: Identifier "m"  >Whitespace" "
              insteadofKeyword: Insteadof "insteadof"  >Whitespace" "
              traits: SeparatedNodeList
                - NameNode
                  token: Identifier "V"
              semicolon: ';' ";"  >Whitespace" "
            - TraitAliasNode
              trait: NameNode
                token: Identifier "V"
              doubleColon: DoubleColon "::"
              method: IdentifierNode
                token: Identifier "m"  >Whitespace" "
              asKeyword: As "as"  >Whitespace" "
              modifier: Protected "protected"  >Whitespace" "
              alias: IdentifierNode
                token: Identifier "n"
              semicolon: ';' ";"  >Whitespace" "
            - TraitAliasNode
              method: IdentifierNode
                token: Identifier "m"  >Whitespace" "
              asKeyword: As "as"  >Whitespace" "
              modifier: Public "public"
              semicolon: ';' ";"  >Whitespace" "
            - TraitAliasNode
              method: IdentifierNode
                token: Identifier "m"  >Whitespace" "
              asKeyword: As "as"  >Whitespace" "
              alias: IdentifierNode
                token: Identifier "o"
              semicolon: ';' ";"  >Whitespace" "
          closeBrace: '}' "}"  >EndOfLine"\n"
        - ClassConstNode
          attributes: NodeList
          modifiers: ModifiersNode
          constKeyword: Const "const"  <Whitespace"\t"  >Whitespace" "
          items: SeparatedNodeList
            - ConstItemNode
              name: IdentifierNode
                token: Identifier "X"  >Whitespace" "
              equals: '=' "="  >Whitespace" "
              value: IntegerNode
                token: Integer "1"
            - ',' ","  >Whitespace" "
            - ConstItemNode
              name: IdentifierNode
                token: Identifier "Y"  >Whitespace" "
              equals: '=' "="  >Whitespace" "
              value: IntegerNode
                token: Integer "2"
          semicolon: ';' ";"  >Whitespace" "
        - ClassConstNode
          attributes: NodeList
          modifiers: ModifiersNode
            - Final "final"  >Whitespace" "
            - Public "public"  >Whitespace" "
          constKeyword: Const "const"  >Whitespace" "
          type: NamedTypeNode
            name: NameNode
              token: Identifier "int"  >Whitespace" "
          items: SeparatedNodeList
            - ConstItemNode
              name: IdentifierNode
                token: Identifier "Z"  >Whitespace" "
              equals: '=' "="  >Whitespace" "
              value: IntegerNode
                token: Integer "3"
          semicolon: ';' ";"  >EndOfLine"\n"
        - PropertyNode
          attributes: NodeList
          modifiers: ModifiersNode
            - Var "var"  <Whitespace"\t"  >Whitespace" "
          items: SeparatedNodeList
            - PropertyItemNode
              name: Variable "$a"
          semicolon: ';' ";"  >Whitespace" "
        - PropertyNode
          attributes: NodeList
          modifiers: ModifiersNode
            - Public "public"  >Whitespace" "
            - Static "static"  >Whitespace" "
          type: NullableTypeNode
            question: '?' "?"
            type: NamedTypeNode
              name: NameNode
                token: Identifier "int"  >Whitespace" "
          items: SeparatedNodeList
            - PropertyItemNode
              name: Variable "$b"  >Whitespace" "
              equals: '=' "="  >Whitespace" "
              default: IntegerNode
                token: Integer "1"
            - ',' ","  >Whitespace" "
            - PropertyItemNode
              name: Variable "$c"
          semicolon: ';' ";"  >Whitespace" "
        - PropertyNode
          attributes: NodeList
          modifiers: ModifiersNode
            - PrivateSet "private(set)"  >Whitespace" "
            - Readonly "readonly"  >Whitespace" "
          type: NamedTypeNode
            name: NameNode
              token: Identifier "string"  >Whitespace" "
          items: SeparatedNodeList
            - PropertyItemNode
              name: Variable "$d"  >Whitespace" "
          openBrace: '{' "{"  >Whitespace" "
          hooks: NodeList
            - PropertyHookNode
              attributes: NodeList
              modifiers: ModifiersNode
              name: IdentifierNode
                token: Identifier "get"  >Whitespace" "
              doubleArrow: DoubleArrow "=>"  >Whitespace" "
              expression: PropertyFetchNode
                object: VariableNode
                  name: Variable "$this"
                operator: ObjectOperator "->"
                name: IdentifierNode
                  token: Identifier "d"
              semicolon: ';' ";"  >Whitespace" "
            - PropertyHookNode
              attributes: NodeList
              modifiers: ModifiersNode
              name: IdentifierNode
                token: Identifier "set"
              openParen: '(' "("
              parameters: SeparatedNodeList
                - ParameterNode
                  attributes: NodeList
                  modifiers: ModifiersNode
                  variable: VariableNode
                    name: Variable "$v"
              closeParen: ')' ")"  >Whitespace" "
              body: BlockNode
                openBrace: '{' "{"  >Whitespace" "
                statements: NodeList
                  - ExpressionStatementNode
                    expression: AssignmentNode
                      target: PropertyFetchNode
                        object: VariableNode
                          name: Variable "$this"
                        operator: ObjectOperator "->"
                        name: IdentifierNode
                          token: Identifier "d"  >Whitespace" "
                      operator: '=' "="  >Whitespace" "
                      expression: VariableNode
                        name: Variable "$v"
                    semicolon: ';' ";"  >Whitespace" "
                closeBrace: '}' "}"  >Whitespace" "
          closeBrace: '}' "}"  >EndOfLine"\n"
        - MethodNode
          attributes: NodeList
          modifiers: ModifiersNode
            - Abstract "abstract"  <Whitespace"\t"  >Whitespace" "
            - Protected "protected"  >Whitespace" "
          functionKeyword: Function "function"  >Whitespace" "
          name: IdentifierNode
            token: Identifier "m"
          openParen: '(' "("
          parameters: SeparatedNodeList
          closeParen: ')' ")"
          colon: ':' ":"  >Whitespace" "
          returnType: NamedTypeNode
            name: NameNode
              token: Identifier "void"
          semicolon: ';' ";"  >EndOfLine"\n"
        - MethodNode
          attributes: NodeList
          modifiers: ModifiersNode
            - Public "public"  <Whitespace"\t"  >Whitespace" "
            - Static "static"  >Whitespace" "
          functionKeyword: Function "function"  >Whitespace" "
          ampersand: AmpersandNotFollowedByVariableOrVariadic "&"
          name: IdentifierNode
            token: Identifier "n"
          openParen: '(' "("
          parameters: SeparatedNodeList
          closeParen: ')' ")"
          colon: ':' ":"  >Whitespace" "
          returnType: NamedTypeNode
            name: NameNode
              token: Static "static"  >Whitespace" "
          body: BlockNode
            openBrace: '{' "{"  >Whitespace" "
            statements: NodeList
            closeBrace: '}' "}"  >EndOfLine"\n"
        - MethodNode
          attributes: NodeList
          modifiers: ModifiersNode
            - Final "final"  <Whitespace"\t"  >Whitespace" "
          functionKeyword: Function "function"  >Whitespace" "
          name: IdentifierNode
            token: Identifier "o"
          openParen: '(' "("
          parameters: SeparatedNodeList
          closeParen: ')' ")"  >Whitespace" "
          body: BlockNode
            openBrace: '{' "{"  >Whitespace" "
            statements: NodeList
            closeBrace: '}' "}"  >EndOfLine"\n"
      closeBrace: '}' "}"  >EndOfLine"\n"
    - InterfaceNode
      attributes: NodeList
      interfaceKeyword: Interface "interface"  >Whitespace" "
      name: IdentifierNode
        token: Identifier "I"  >Whitespace" "
      extendsKeyword: Extends "extends"  >Whitespace" "
      extends: SeparatedNodeList
        - NameNode
          token: Identifier "J"
        - ',' ","  >Whitespace" "
        - NameNode
          token: Identifier "K"  >Whitespace" "
      openBrace: '{' "{"  >Whitespace" "
      members: NodeList
        - ClassConstNode
          attributes: NodeList
          modifiers: ModifiersNode
          constKeyword: Const "const"  >Whitespace" "
          items: SeparatedNodeList
            - ConstItemNode
              name: IdentifierNode
                token: Identifier "A"  >Whitespace" "
              equals: '=' "="  >Whitespace" "
              value: IntegerNode
                token: Integer "1"
          semicolon: ';' ";"  >Whitespace" "
        - MethodNode
          attributes: NodeList
          modifiers: ModifiersNode
            - Public "public"  >Whitespace" "
          functionKeyword: Function "function"  >Whitespace" "
          name: IdentifierNode
            token: Identifier "m"
          openParen: '(' "("
          parameters: SeparatedNodeList
          closeParen: ')' ")"
          semicolon: ';' ";"  >Whitespace" "
      closeBrace: '}' "}"  >EndOfLine"\n"
    - TraitNode
      attributes: NodeList
      traitKeyword: Trait "trait"  >Whitespace" "
      name: IdentifierNode
        token: Identifier "T"  >Whitespace" "
      openBrace: '{' "{"  >Whitespace" "
      members: NodeList
        - MethodNode
          attributes: NodeList
          modifiers: ModifiersNode
            - Public "public"  >Whitespace" "
          functionKeyword: Function "function"  >Whitespace" "
          name: IdentifierNode
            token: Identifier "m"
          openParen: '(' "("
          parameters: SeparatedNodeList
          closeParen: ')' ")"  >Whitespace" "
          body: BlockNode
            openBrace: '{' "{"  >Whitespace" "
            statements: NodeList
            closeBrace: '}' "}"  >Whitespace" "
      closeBrace: '}' "}"  >EndOfLine"\n"
    - EnumNode
      attributes: NodeList
      enumKeyword: Enum "enum"  >Whitespace" "
      name: IdentifierNode
        token: Identifier "E"
      colon: ':' ":"  >Whitespace" "
      scalarType: NamedTypeNode
        name: NameNode
          token: Identifier "string"  >Whitespace" "
      implementsKeyword: Implements "implements"  >Whitespace" "
      implements: SeparatedNodeList
        - NameNode
          token: Identifier "I"  >Whitespace" "
      openBrace: '{' "{"  >Whitespace" "
      members: NodeList
        - EnumCaseNode
          attributes: NodeList
          caseKeyword: Case "case"  >Whitespace" "
          name: IdentifierNode
            token: Identifier "A"  >Whitespace" "
          equals: '=' "="  >Whitespace" "
          value: StringNode
            token: ConstantEncapsedString "'a'"
          semicolon: ';' ";"  >Whitespace" "
        - EnumCaseNode
          attributes: NodeList
          caseKeyword: Case "case"  >Whitespace" "
          name: IdentifierNode
            token: Identifier "B"
          semicolon: ';' ";"  >Whitespace" "
        - ClassConstNode
          attributes: NodeList
          modifiers: ModifiersNode
          constKeyword: Const "const"  >Whitespace" "
          items: SeparatedNodeList
            - ConstItemNode
              name: IdentifierNode
                token: Identifier "C"  >Whitespace" "
              equals: '=' "="  >Whitespace" "
              value: ClassConstantFetchNode
                class: NameNode
                  token: Identifier "self"
                doubleColon: DoubleColon "::"
                name: IdentifierNode
                  token: Identifier "A"
          semicolon: ';' ";"  >Whitespace" "
        - MethodNode
          attributes: NodeList
          modifiers: ModifiersNode
            - Public "public"  >Whitespace" "
          functionKeyword: Function "function"  >Whitespace" "
          name: IdentifierNode
            token: Identifier "m"
          openParen: '(' "("
          parameters: SeparatedNodeList
          closeParen: ')' ")"  >Whitespace" "
          body: BlockNode
            openBrace: '{' "{"  >Whitespace" "
            statements: NodeList
            closeBrace: '}' "}"  >Whitespace" "
      closeBrace: '}' "}"  >EndOfLine"\n"
    - EnumNode
      attributes: NodeList
      enumKeyword: Enum "enum"  >Whitespace" "
      name: IdentifierNode
        token: Identifier "F"  >Whitespace" "
      openBrace: '{' "{"  >Whitespace" "
      members: NodeList
        - EnumCaseNode
          attributes: NodeList
          caseKeyword: Case "case"  >Whitespace" "
          name: IdentifierNode
            token: Identifier "A"
          semicolon: ';' ";"  >Whitespace" "
      closeBrace: '}' "}"  >EndOfLine"\n"
    - ClassNode
      attributes: NodeList
      modifiers: ModifiersNode
        - Final "final"  >Whitespace" "
      classKeyword: ClassKeyword "class"  >Whitespace" "
      name: IdentifierNode
        token: Identifier "G"  >Whitespace" "
      openBrace: '{' "{"  >Whitespace" "
      members: NodeList
        - MethodNode
          attributes: NodeList
          modifiers: ModifiersNode
            - Public "public"  >Whitespace" "
          functionKeyword: Function "function"  >Whitespace" "
          name: IdentifierNode
            token: Identifier "__construct"
          openParen: '(' "("
          parameters: SeparatedNodeList
            - ParameterNode
              attributes: NodeList
              modifiers: ModifiersNode
                - Public "public"  >Whitespace" "
                - Readonly "readonly"  >Whitespace" "
              type: NamedTypeNode
                name: NameNode
                  token: Identifier "int"  >Whitespace" "
              variable: VariableNode
                name: Variable "$a"
            - ',' ","  >Whitespace" "
            - ParameterNode
              attributes: NodeList
              modifiers: ModifiersNode
                - Protected "protected"  >Whitespace" "
              variable: VariableNode
                name: Variable "$b"  >Whitespace" "
              equals: '=' "="  >Whitespace" "
              default: IntegerNode
                token: Integer "1"
            - ',' ","  >Whitespace" "
            - ParameterNode
              attributes: NodeList
              modifiers: ModifiersNode
                - PrivateSet "private(set)"  >Whitespace" "
              type: NamedTypeNode
                name: NameNode
                  token: Identifier "int"  >Whitespace" "
              variable: VariableNode
                name: Variable "$c"  >Whitespace" "
              openBrace: '{' "{"  >Whitespace" "
              hooks: NodeList
                - PropertyHookNode
                  attributes: NodeList
                  modifiers: ModifiersNode
                  name: IdentifierNode
                    token: Identifier "get"  >Whitespace" "
                  doubleArrow: DoubleArrow "=>"  >Whitespace" "
                  expression: IntegerNode
                    token: Integer "1"
                  semicolon: ';' ";"  >Whitespace" "
              closeBrace: '}' "}"
          closeParen: ')' ")"  >Whitespace" "
          body: BlockNode
            openBrace: '{' "{"  >Whitespace" "
            statements: NodeList
            closeBrace: '}' "}"  >Whitespace" "
      closeBrace: '}' "}"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$x"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: NewNode
          newKeyword: New "new"  >Whitespace" "
          class: AnonymousClassNode
            attributes: NodeList
            modifiers: ModifiersNode
            classKeyword: ClassKeyword "class"
            arguments: ArgumentListNode
              openParen: '(' "("
              items: SeparatedNodeList
                - ArgumentNode
                  value: IntegerNode
                    token: Integer "1"
              closeParen: ')' ")"  >Whitespace" "
            extendsKeyword: Extends "extends"  >Whitespace" "
            extends: NameNode
              token: Identifier "A"  >Whitespace" "
            openBrace: '{' "{"  >Whitespace" "
            members: NodeList
            closeBrace: '}' "}"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$y"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: NewNode
          newKeyword: New "new"  >Whitespace" "
          class: AnonymousClassNode
            attributes: NodeList
            modifiers: ModifiersNode
              - Readonly "readonly"  >Whitespace" "
            classKeyword: ClassKeyword "class"  >Whitespace" "
            openBrace: '{' "{"  >Whitespace" "
            members: NodeList
            closeBrace: '}' "}"
      semicolon: ';' ";"
  endOfFile: EndOfFile ""
