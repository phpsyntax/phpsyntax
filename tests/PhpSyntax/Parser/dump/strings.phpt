<?php declare(strict_types=1);

// interpolated strings, heredoc, nowdoc, shell exec

use PhpSyntax\Parser;
use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';

$input = <<<'XX'
	<?php
	"a $b c {$d->e} ${f} $g[0] $g[key] $g[-1] $g[$i] $h->i $j?->k ${l['x']} {$m[1][2]} {$n /* c */ }";
	$a = <<<EOT
		x $b
		 y
		EOT;
	$c = <<<'EOT'
	raw $d
	EOT;
	$e = <<<EOT
	EOT;
	`ls $f {$g}`;
	XX;

$file = (new Parser)->parse($input);
Assert::same($input, (string) $file);
Assert::same(loadExpected(__FILE__, __COMPILER_HALT_OFFSET__), Dumper::dump($file));

__halt_compiler();
FileNode
  statements: NodeList
    - ExpressionStatementNode
      expression: InterpolatedStringNode
        openQuote: '"' "\""  <OpenTag"<?php\n"
        parts: NodeList
          - InterpolatedStringPartNode
            token: EncapsedAndWhitespace "a "
          - VariableNode
            name: Variable "$b"
          - InterpolatedStringPartNode
            token: EncapsedAndWhitespace " c "
          - InterpolationNode
            openBrace: CurlyOpen "{"
            expression: PropertyFetchNode
              object: VariableNode
                name: Variable "$d"
              operator: ObjectOperator "->"
              name: IdentifierNode
                token: Identifier "e"
            closeBrace: '}' "}"
          - InterpolatedStringPartNode
            token: EncapsedAndWhitespace " "
          - InterpolationNode
            openBrace: DollarOpenCurlyBraces "${"
            expression: VariableNode
              name: StringVariableName "f"
            closeBrace: '}' "}"
          - InterpolatedStringPartNode
            token: EncapsedAndWhitespace " "
          - ArrayAccessNode
            expression: VariableNode
              name: Variable "$g"
            openBracket: '[' "["
            index: IntegerNode
              token: NumericString "0"
            closeBracket: ']' "]"
          - InterpolatedStringPartNode
            token: EncapsedAndWhitespace " "
          - ArrayAccessNode
            expression: VariableNode
              name: Variable "$g"
            openBracket: '[' "["
            index: UnquotedStringNode
              token: Identifier "key"
            closeBracket: ']' "]"
          - InterpolatedStringPartNode
            token: EncapsedAndWhitespace " "
          - ArrayAccessNode
            expression: VariableNode
              name: Variable "$g"
            openBracket: '[' "["
            index: UnaryOpNode
              operator: '-' "-"
              expression: IntegerNode
                token: NumericString "1"
            closeBracket: ']' "]"
          - InterpolatedStringPartNode
            token: EncapsedAndWhitespace " "
          - ArrayAccessNode
            expression: VariableNode
              name: Variable "$g"
            openBracket: '[' "["
            index: VariableNode
              name: Variable "$i"
            closeBracket: ']' "]"
          - InterpolatedStringPartNode
            token: EncapsedAndWhitespace " "
          - PropertyFetchNode
            object: VariableNode
              name: Variable "$h"
            operator: ObjectOperator "->"
            name: IdentifierNode
              token: Identifier "i"
          - InterpolatedStringPartNode
            token: EncapsedAndWhitespace " "
          - PropertyFetchNode
            object: VariableNode
              name: Variable "$j"
            operator: NullsafeObjectOperator "?->"
            name: IdentifierNode
              token: Identifier "k"
          - InterpolatedStringPartNode
            token: EncapsedAndWhitespace " "
          - InterpolationNode
            openBrace: DollarOpenCurlyBraces "${"
            expression: ArrayAccessNode
              expression: VariableNode
                name: StringVariableName "l"
              openBracket: '[' "["
              index: StringNode
                token: ConstantEncapsedString "'x'"
              closeBracket: ']' "]"
            closeBrace: '}' "}"
          - InterpolatedStringPartNode
            token: EncapsedAndWhitespace " "
          - InterpolationNode
            openBrace: CurlyOpen "{"
            expression: ArrayAccessNode
              expression: ArrayAccessNode
                expression: VariableNode
                  name: Variable "$m"
                openBracket: '[' "["
                index: IntegerNode
                  token: Integer "1"
                closeBracket: ']' "]"
              openBracket: '[' "["
              index: IntegerNode
                token: Integer "2"
              closeBracket: ']' "]"
            closeBrace: '}' "}"
          - InterpolatedStringPartNode
            token: EncapsedAndWhitespace " "
          - InterpolationNode
            openBrace: CurlyOpen "{"
            expression: VariableNode
              name: Variable "$n"  >Whitespace*" " Comment*"/* c */" Whitespace*" "
            closeBrace: '}' "}"
        closeQuote: '"' "\""
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$a"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: HeredocNode
          openDelimiter: StartHeredoc "<<<EOT\n"
          parts: NodeList
            - InterpolatedStringPartNode
              token: EncapsedAndWhitespace "\tx "
            - VariableNode
              name: Variable "$b"
            - InterpolatedStringPartNode
              token: EncapsedAndWhitespace "\n\t y\n"
          closeDelimiter: EndHeredoc "\tEOT"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$c"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: HeredocNode
          openDelimiter: StartHeredoc "<<<'EOT'\n"
          parts: NodeList
            - InterpolatedStringPartNode
              token: EncapsedAndWhitespace "raw $d\n"
          closeDelimiter: EndHeredoc "EOT"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$e"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: HeredocNode
          openDelimiter: StartHeredoc "<<<EOT\n"
          parts: NodeList
          closeDelimiter: EndHeredoc "EOT"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: ShellExecNode
        openBacktick: '`' "`"
        parts: NodeList
          - InterpolatedStringPartNode
            token: EncapsedAndWhitespace "ls "
          - VariableNode
            name: Variable "$f"
          - InterpolatedStringPartNode
            token: EncapsedAndWhitespace " "
          - InterpolationNode
            openBrace: CurlyOpen "{"
            expression: VariableNode
              name: Variable "$g"
            closeBrace: '}' "}"
        closeBacktick: '`' "`"
      semicolon: ';' ";"
  endOfFile: EndOfFile ""
