<?php declare(strict_types=1);

// names, identifiers, scalars and constants

use PhpSyntax\Parser;
use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';

$input = <<<'XX'
	<?php
	$a = 1 + 0x1F + 0b11 + 0o17 + 1_000;
	$b = .5 + 1.5e3;
	$c = 'single' . "double" . b'binary';
	$d = FOO . \Foo\BAR . namespace\BAZ . __LINE__ . __CLASS__;
	$e = true && null;
	__halt_compiler(); data
	XX;

$file = (new Parser)->parse($input);
Assert::same($input, (string) $file);
Assert::same(loadExpected(__FILE__, __COMPILER_HALT_OFFSET__), Dumper::dump($file));

__halt_compiler();
FileNode
  statements: NodeList
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$a"  <OpenTag"<?php\n"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: BinaryOpNode
          left: BinaryOpNode
            left: BinaryOpNode
              left: BinaryOpNode
                left: IntegerNode
                  token: Integer "1"  >Whitespace" "
                operator: '+' "+"  >Whitespace" "
                right: IntegerNode
                  token: Integer "0x1F"  >Whitespace" "
              operator: '+' "+"  >Whitespace" "
              right: IntegerNode
                token: Integer "0b11"  >Whitespace" "
            operator: '+' "+"  >Whitespace" "
            right: IntegerNode
              token: Integer "0o17"  >Whitespace" "
          operator: '+' "+"  >Whitespace" "
          right: IntegerNode
            token: Integer "1_000"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$b"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: BinaryOpNode
          left: FloatNode
            token: Float ".5"  >Whitespace" "
          operator: '+' "+"  >Whitespace" "
          right: FloatNode
            token: Float "1.5e3"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$c"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: BinaryOpNode
          left: BinaryOpNode
            left: StringNode
              token: ConstantEncapsedString "'single'"  >Whitespace" "
            operator: '.' "."  >Whitespace" "
            right: StringNode
              token: ConstantEncapsedString "\"double\""  >Whitespace" "
          operator: '.' "."  >Whitespace" "
          right: StringNode
            token: ConstantEncapsedString "b'binary'"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$d"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: BinaryOpNode
          left: BinaryOpNode
            left: BinaryOpNode
              left: BinaryOpNode
                left: ConstantFetchNode
                  name: NameNode
                    token: Identifier "FOO"  >Whitespace" "
                operator: '.' "."  >Whitespace" "
                right: ConstantFetchNode
                  name: NameNode
                    token: NameFullyQualified "\\Foo\\BAR"  >Whitespace" "
              operator: '.' "."  >Whitespace" "
              right: ConstantFetchNode
                name: NameNode
                  token: NameRelative "namespace\\BAZ"  >Whitespace" "
            operator: '.' "."  >Whitespace" "
            right: MagicConstantNode
              token: MagicLine "__LINE__"  >Whitespace" "
          operator: '.' "."  >Whitespace" "
          right: MagicConstantNode
            token: MagicClass "__CLASS__"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$e"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: BinaryOpNode
          left: BooleanNode
            token: Identifier "true"  >Whitespace" "
          operator: BooleanAnd "&&"  >Whitespace" "
          right: NullNode
            token: Identifier "null"
      semicolon: ';' ";"  >EndOfLine"\n"
    - HaltCompilerNode
      haltKeyword: HaltCompiler "__halt_compiler"
      openParen: '(' "("
      closeParen: ')' ")"
      semicolon: ';' ";"
      data: HaltCompilerData " data"
  endOfFile: EndOfFile ""
