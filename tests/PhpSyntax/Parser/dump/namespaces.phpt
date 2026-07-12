<?php declare(strict_types=1);

// namespaces (nested form), use imports, const, declare, inline HTML

use PhpSyntax\Parser;
use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';

$input = <<<'XX'
	<html>
	<?php declare(strict_types=1);
	namespace A\B;
	use C\D, E\F as G;
	use function H\i;
	use const J\K;
	use L\{M, N as O, function p, const Q,};
	use function R\{s, t};
	const U = 1, V = 2;
	namespace W {
		$x;
	}
	namespace {
		$y;
	}
	XX;

$file = (new Parser)->parse($input);
Assert::same($input, (string) $file);
Assert::same(loadExpected(__FILE__, __COMPILER_HALT_OFFSET__), Dumper::dump($file));

__halt_compiler();
FileNode
  statements: NodeList
    - InlineHtmlNode
      html: InlineHtml "<html>\n"
    - DeclareNode
      declareKeyword: Declare "declare"  <OpenTag"<?php "
      openParen: '(' "("
      items: SeparatedNodeList
        - DeclareItemNode
          name: IdentifierNode
            token: Identifier "strict_types"
          equals: '=' "="
          value: IntegerNode
            token: Integer "1"
      closeParen: ')' ")"
      semicolon: ';' ";"  >EndOfLine"\n"
    - NamespaceNode
      namespaceKeyword: NamespaceKeyword "namespace"  >Whitespace" "
      name: NameNode
        token: NameQualified "A\\B"
      semicolon: ';' ";"  >EndOfLine"\n"
      statements: NodeList
        - UseNode
          useKeyword: Use "use"  >Whitespace" "
          items: SeparatedNodeList
            - UseItemNode
              name: NameNode
                token: NameQualified "C\\D"
            - ',' ","  >Whitespace" "
            - UseItemNode
              name: NameNode
                token: NameQualified "E\\F"  >Whitespace" "
              asKeyword: As "as"  >Whitespace" "
              alias: IdentifierNode
                token: Identifier "G"
          semicolon: ';' ";"  >EndOfLine"\n"
        - UseNode
          useKeyword: Use "use"  >Whitespace" "
          type: Function "function"  >Whitespace" "
          items: SeparatedNodeList
            - UseItemNode
              name: NameNode
                token: NameQualified "H\\i"
          semicolon: ';' ";"  >EndOfLine"\n"
        - UseNode
          useKeyword: Use "use"  >Whitespace" "
          type: Const "const"  >Whitespace" "
          items: SeparatedNodeList
            - UseItemNode
              name: NameNode
                token: NameQualified "J\\K"
          semicolon: ';' ";"  >EndOfLine"\n"
        - UseNode
          useKeyword: Use "use"  >Whitespace" "
          prefix: NameNode
            token: Identifier "L"
          namespaceSeparator: NamespaceSeparator "\\"
          openBrace: '{' "{"
          items: SeparatedNodeList
            - UseItemNode
              name: NameNode
                token: Identifier "M"
            - ',' ","  >Whitespace" "
            - UseItemNode
              name: NameNode
                token: Identifier "N"  >Whitespace" "
              asKeyword: As "as"  >Whitespace" "
              alias: IdentifierNode
                token: Identifier "O"
            - ',' ","  >Whitespace" "
            - UseItemNode
              type: Function "function"  >Whitespace" "
              name: NameNode
                token: Identifier "p"
            - ',' ","  >Whitespace" "
            - UseItemNode
              type: Const "const"  >Whitespace" "
              name: NameNode
                token: Identifier "Q"
            - ',' ","
          closeBrace: '}' "}"
          semicolon: ';' ";"  >EndOfLine"\n"
        - UseNode
          useKeyword: Use "use"  >Whitespace" "
          type: Function "function"  >Whitespace" "
          prefix: NameNode
            token: Identifier "R"
          namespaceSeparator: NamespaceSeparator "\\"
          openBrace: '{' "{"
          items: SeparatedNodeList
            - UseItemNode
              name: NameNode
                token: Identifier "s"
            - ',' ","  >Whitespace" "
            - UseItemNode
              name: NameNode
                token: Identifier "t"
          closeBrace: '}' "}"
          semicolon: ';' ";"  >EndOfLine"\n"
        - ConstNode
          attributes: NodeList
          constKeyword: Const "const"  >Whitespace" "
          items: SeparatedNodeList
            - ConstItemNode
              name: IdentifierNode
                token: Identifier "U"  >Whitespace" "
              equals: '=' "="  >Whitespace" "
              value: IntegerNode
                token: Integer "1"
            - ',' ","  >Whitespace" "
            - ConstItemNode
              name: IdentifierNode
                token: Identifier "V"  >Whitespace" "
              equals: '=' "="  >Whitespace" "
              value: IntegerNode
                token: Integer "2"
          semicolon: ';' ";"  >EndOfLine"\n"
    - NamespaceNode
      namespaceKeyword: NamespaceKeyword "namespace"  >Whitespace" "
      name: NameNode
        token: Identifier "W"  >Whitespace" "
      openBrace: '{' "{"  >EndOfLine"\n"
      statements: NodeList
        - ExpressionStatementNode
          expression: VariableNode
            name: Variable "$x"  <Whitespace"\t"
          semicolon: ';' ";"  >EndOfLine"\n"
      closeBrace: '}' "}"  >EndOfLine"\n"
    - NamespaceNode
      namespaceKeyword: NamespaceKeyword "namespace"  >Whitespace" "
      openBrace: '{' "{"  >EndOfLine"\n"
      statements: NodeList
        - ExpressionStatementNode
          expression: VariableNode
            name: Variable "$y"  <Whitespace"\t"
          semicolon: ';' ";"  >EndOfLine"\n"
      closeBrace: '}' "}"
  endOfFile: EndOfFile ""
