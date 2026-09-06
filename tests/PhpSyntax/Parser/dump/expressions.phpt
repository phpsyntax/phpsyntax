<?php declare(strict_types=1);

// variables, accesses, calls, arrays, operators and the remaining expressions

use PhpSyntax\Parser;
use Tester\Assert;

require __DIR__ . '/../../../bootstrap.php';

$input = <<<'XX'
	<?php
	$a; $$a; ${'a' . 'b'}; $a[1]; $a[]; $a->b; $a?->b; $a->{'b'}; $a->$b; A::$b; $a::$b; A::$$b; A::${'b'};
	A::B; A::class; A::{$x}; $a::B; static::B;
	f(1, ...$a, name: 2); $f(); $a->m(); $a?->m(); A::m(); $a::m(); A::{'m'}(); $a->{'m'}(); 'f'(); (fn() => 1)();
	new A(1); new A; new static; new $b; new ($c); new class(1) extends B implements C { }; f(...); $a->m(...);
	f(?); f(1, ?); f(name: ?); f(?, ...); new A(?); $a->m(?); A::m(?);
	[1, 'k' => 2, ...$x, &$y, 3 => &$z]; array(1,); [, $b] = $x; list($a, , $b) = $c; [$a, [$b]] = $c; [$k => $v] = $c;
	$a = $b += $c .= $d ??= $e; $a = &$b; $a++; $a--; ++$a; --$a; +$a; -$a; !$a; ~$a; @$a;
	$a + $b - $c * $d / $e % $f ** $g; $a . $b; $a & $b | $c ^ $d << $e >> $f;
	$a == $b; $a != $b; $a === $b; $a !== $b; $a < $b; $a <= $b; $a > $b; $a >= $b; $a <=> $b; $a && $b || $c and $d or $e xor $f;
	$a ? $b : $c; $a ?: $c; $a ?? $b; $a instanceof B; $a instanceof $b; $a instanceof (B); $a |> f(...);
	(int) $a; ( int ) $a; (float) $a; (string) $a; (array) $a; (object) $a; (bool) $a; (void) f();
	isset($a, $b,); empty($a); eval('1'); include 'a'; include_once 'a'; require 'a'; require_once 'a';
	exit; exit(1); die('x'); print $a; throw new E; clone $a; clone($a, [1]);
	match ($a) { 1, 2 => 'x', default => 'y', };
	function () { }; static function (&$a) use ($b, &$c): int { return 1; }; fn($a) => $a; static fn&() => 1;
	yield; yield $a; yield $k => $v; yield from $g; `ls $a`;
	XX;

$file = (new Parser)->parse($input);
Assert::same($input, (string) $file);
Assert::same(loadExpected(__FILE__, __COMPILER_HALT_OFFSET__), Dumper::dump($file));

__halt_compiler();
FileNode
  statements: NodeList
    - ExpressionStatementNode
      expression: VariableNode
        name: Variable "$a"  <OpenTag"<?php\n"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: VariableNode
        dollar: '$' "$"
        name: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: VariableNode
        dollar: '$' "$"
        openBrace: '{' "{"
        name: BinaryOpNode
          left: StringNode
            token: ConstantEncapsedString "'a'"  >Whitespace" "
          operator: '.' "."  >Whitespace" "
          right: StringNode
            token: ConstantEncapsedString "'b'"
        closeBrace: '}' "}"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: ArrayAccessNode
        expression: VariableNode
          name: Variable "$a"
        openBracket: '[' "["
        index: IntegerNode
          token: Integer "1"
        closeBracket: ']' "]"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: ArrayAccessNode
        expression: VariableNode
          name: Variable "$a"
        openBracket: '[' "["
        closeBracket: ']' "]"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: PropertyFetchNode
        object: VariableNode
          name: Variable "$a"
        operator: ObjectOperator "->"
        name: IdentifierNode
          token: Identifier "b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: PropertyFetchNode
        object: VariableNode
          name: Variable "$a"
        operator: NullsafeObjectOperator "?->"
        name: IdentifierNode
          token: Identifier "b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: PropertyFetchNode
        object: VariableNode
          name: Variable "$a"
        operator: ObjectOperator "->"
        openBrace: '{' "{"
        name: StringNode
          token: ConstantEncapsedString "'b'"
        closeBrace: '}' "}"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: PropertyFetchNode
        object: VariableNode
          name: Variable "$a"
        operator: ObjectOperator "->"
        name: VariableNode
          name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: StaticPropertyFetchNode
        class: NameNode
          token: Identifier "A"
        doubleColon: DoubleColon "::"
        name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: StaticPropertyFetchNode
        class: VariableNode
          name: Variable "$a"
        doubleColon: DoubleColon "::"
        name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: StaticPropertyFetchNode
        class: NameNode
          token: Identifier "A"
        doubleColon: DoubleColon "::"
        dollar: '$' "$"
        name: VariableNode
          name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: StaticPropertyFetchNode
        class: NameNode
          token: Identifier "A"
        doubleColon: DoubleColon "::"
        dollar: '$' "$"
        openBrace: '{' "{"
        name: StringNode
          token: ConstantEncapsedString "'b'"
        closeBrace: '}' "}"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: ClassConstantFetchNode
        class: NameNode
          token: Identifier "A"
        doubleColon: DoubleColon "::"
        name: IdentifierNode
          token: Identifier "B"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: ClassConstantFetchNode
        class: NameNode
          token: Identifier "A"
        doubleColon: DoubleColon "::"
        name: IdentifierNode
          token: ClassKeyword "class"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: ClassConstantFetchNode
        class: NameNode
          token: Identifier "A"
        doubleColon: DoubleColon "::"
        openBrace: '{' "{"
        name: VariableNode
          name: Variable "$x"
        closeBrace: '}' "}"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: ClassConstantFetchNode
        class: VariableNode
          name: Variable "$a"
        doubleColon: DoubleColon "::"
        name: IdentifierNode
          token: Identifier "B"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: ClassConstantFetchNode
        class: NameNode
          token: Static "static"
        doubleColon: DoubleColon "::"
        name: IdentifierNode
          token: Identifier "B"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: FunctionCallNode
        name: NameNode
          token: Identifier "f"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
            - ArgumentNode
              value: IntegerNode
                token: Integer "1"
            - ',' ","  >Whitespace" "
            - ArgumentNode
              ellipsis: Ellipsis "..."
              value: VariableNode
                name: Variable "$a"
            - ',' ","  >Whitespace" "
            - ArgumentNode
              name: IdentifierNode
                token: Identifier "name"
              colon: ':' ":"  >Whitespace" "
              value: IntegerNode
                token: Integer "2"
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: FunctionCallNode
        name: VariableNode
          name: Variable "$f"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: MethodCallNode
        object: VariableNode
          name: Variable "$a"
        operator: ObjectOperator "->"
        name: IdentifierNode
          token: Identifier "m"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: MethodCallNode
        object: VariableNode
          name: Variable "$a"
        operator: NullsafeObjectOperator "?->"
        name: IdentifierNode
          token: Identifier "m"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: StaticMethodCallNode
        class: NameNode
          token: Identifier "A"
        doubleColon: DoubleColon "::"
        name: IdentifierNode
          token: Identifier "m"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: StaticMethodCallNode
        class: VariableNode
          name: Variable "$a"
        doubleColon: DoubleColon "::"
        name: IdentifierNode
          token: Identifier "m"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: StaticMethodCallNode
        class: NameNode
          token: Identifier "A"
        doubleColon: DoubleColon "::"
        openBrace: '{' "{"
        name: StringNode
          token: ConstantEncapsedString "'m'"
        closeBrace: '}' "}"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: MethodCallNode
        object: VariableNode
          name: Variable "$a"
        operator: ObjectOperator "->"
        openBrace: '{' "{"
        name: StringNode
          token: ConstantEncapsedString "'m'"
        closeBrace: '}' "}"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: FunctionCallNode
        name: StringNode
          token: ConstantEncapsedString "'f'"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: FunctionCallNode
        name: ParenthesizedNode
          openParen: '(' "("
          expression: ArrowFunctionNode
            attributes: NodeList
            fnKeyword: Fn "fn"
            openParen: '(' "("
            parameters: SeparatedNodeList
            closeParen: ')' ")"  >Whitespace" "
            doubleArrow: DoubleArrow "=>"  >Whitespace" "
            expression: IntegerNode
              token: Integer "1"
          closeParen: ')' ")"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
          closeParen: ')' ")"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: NewNode
        newKeyword: New "new"  >Whitespace" "
        class: NameNode
          token: Identifier "A"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
            - ArgumentNode
              value: IntegerNode
                token: Integer "1"
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: NewNode
        newKeyword: New "new"  >Whitespace" "
        class: NameNode
          token: Identifier "A"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: NewNode
        newKeyword: New "new"  >Whitespace" "
        class: NameNode
          token: Static "static"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: NewNode
        newKeyword: New "new"  >Whitespace" "
        class: VariableNode
          name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: NewNode
        newKeyword: New "new"  >Whitespace" "
        class: ParenthesizedNode
          openParen: '(' "("
          expression: VariableNode
            name: Variable "$c"
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
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
            token: Identifier "B"  >Whitespace" "
          implementsKeyword: Implements "implements"  >Whitespace" "
          implements: SeparatedNodeList
            - NameNode
              token: Identifier "C"  >Whitespace" "
          openBrace: '{' "{"  >Whitespace" "
          members: NodeList
          closeBrace: '}' "}"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: FunctionCallNode
        name: NameNode
          token: Identifier "f"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
            - VariadicPlaceholderNode
              ellipsis: Ellipsis "..."
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: MethodCallNode
        object: VariableNode
          name: Variable "$a"
        operator: ObjectOperator "->"
        name: IdentifierNode
          token: Identifier "m"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
            - VariadicPlaceholderNode
              ellipsis: Ellipsis "..."
          closeParen: ')' ")"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: FunctionCallNode
        name: NameNode
          token: Identifier "f"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
            - ArgumentPlaceholderNode
              question: '?' "?"
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: FunctionCallNode
        name: NameNode
          token: Identifier "f"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
            - ArgumentNode
              value: IntegerNode
                token: Integer "1"
            - ',' ","  >Whitespace" "
            - ArgumentPlaceholderNode
              question: '?' "?"
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: FunctionCallNode
        name: NameNode
          token: Identifier "f"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
            - ArgumentPlaceholderNode
              name: IdentifierNode
                token: Identifier "name"
              colon: ':' ":"  >Whitespace" "
              question: '?' "?"
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: FunctionCallNode
        name: NameNode
          token: Identifier "f"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
            - ArgumentPlaceholderNode
              question: '?' "?"
            - ',' ","  >Whitespace" "
            - VariadicPlaceholderNode
              ellipsis: Ellipsis "..."
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: NewNode
        newKeyword: New "new"  >Whitespace" "
        class: NameNode
          token: Identifier "A"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
            - ArgumentPlaceholderNode
              question: '?' "?"
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: MethodCallNode
        object: VariableNode
          name: Variable "$a"
        operator: ObjectOperator "->"
        name: IdentifierNode
          token: Identifier "m"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
            - ArgumentPlaceholderNode
              question: '?' "?"
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: StaticMethodCallNode
        class: NameNode
          token: Identifier "A"
        doubleColon: DoubleColon "::"
        name: IdentifierNode
          token: Identifier "m"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
            - ArgumentPlaceholderNode
              question: '?' "?"
          closeParen: ')' ")"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: ArrayNode
        openDelimiter: '[' "["
        items: SeparatedNodeList
          - ArrayItemNode
            value: IntegerNode
              token: Integer "1"
          - ',' ","  >Whitespace" "
          - ArrayItemNode
            key: StringNode
              token: ConstantEncapsedString "'k'"  >Whitespace" "
            doubleArrow: DoubleArrow "=>"  >Whitespace" "
            value: IntegerNode
              token: Integer "2"
          - ',' ","  >Whitespace" "
          - ArrayItemNode
            ellipsis: Ellipsis "..."
            value: VariableNode
              name: Variable "$x"
          - ',' ","  >Whitespace" "
          - ArrayItemNode
            ampersand: AmpersandFollowedByVariableOrVariadic "&"
            value: VariableNode
              name: Variable "$y"
          - ',' ","  >Whitespace" "
          - ArrayItemNode
            key: IntegerNode
              token: Integer "3"  >Whitespace" "
            doubleArrow: DoubleArrow "=>"  >Whitespace" "
            ampersand: AmpersandFollowedByVariableOrVariadic "&"
            value: VariableNode
              name: Variable "$z"
        closeDelimiter: ']' "]"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: ArrayNode
        arrayKeyword: Array "array"
        openDelimiter: '(' "("
        items: SeparatedNodeList
          - ArrayItemNode
            value: IntegerNode
              token: Integer "1"
          - ',' ","
        closeDelimiter: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: AssignmentNode
        target: ListNode
          openDelimiter: '[' "["
          items: SeparatedNodeList
            - EmptyArrayItemNode
            - ',' ","  >Whitespace" "
            - ArrayItemNode
              value: VariableNode
                name: Variable "$b"
          closeDelimiter: ']' "]"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: VariableNode
          name: Variable "$x"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: AssignmentNode
        target: ListNode
          listKeyword: List "list"
          openDelimiter: '(' "("
          items: SeparatedNodeList
            - ArrayItemNode
              value: VariableNode
                name: Variable "$a"
            - ',' ","  >Whitespace" "
            - EmptyArrayItemNode
            - ',' ","  >Whitespace" "
            - ArrayItemNode
              value: VariableNode
                name: Variable "$b"
          closeDelimiter: ')' ")"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: VariableNode
          name: Variable "$c"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: AssignmentNode
        target: ListNode
          openDelimiter: '[' "["
          items: SeparatedNodeList
            - ArrayItemNode
              value: VariableNode
                name: Variable "$a"
            - ',' ","  >Whitespace" "
            - ArrayItemNode
              value: ListNode
                openDelimiter: '[' "["
                items: SeparatedNodeList
                  - ArrayItemNode
                    value: VariableNode
                      name: Variable "$b"
                closeDelimiter: ']' "]"
          closeDelimiter: ']' "]"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: VariableNode
          name: Variable "$c"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: AssignmentNode
        target: ListNode
          openDelimiter: '[' "["
          items: SeparatedNodeList
            - ArrayItemNode
              key: VariableNode
                name: Variable "$k"  >Whitespace" "
              doubleArrow: DoubleArrow "=>"  >Whitespace" "
              value: VariableNode
                name: Variable "$v"
          closeDelimiter: ']' "]"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: VariableNode
          name: Variable "$c"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: AssignmentNode
        target: VariableNode
          name: Variable "$a"  >Whitespace" "
        operator: '=' "="  >Whitespace" "
        expression: CombinedAssignmentNode
          target: VariableNode
            name: Variable "$b"  >Whitespace" "
          operator: PlusEqual "+="  >Whitespace" "
          expression: CombinedAssignmentNode
            target: VariableNode
              name: Variable "$c"  >Whitespace" "
            operator: ConcatEqual ".="  >Whitespace" "
            expression: CombinedAssignmentNode
              target: VariableNode
                name: Variable "$d"  >Whitespace" "
              operator: CoalesceEqual "??="  >Whitespace" "
              expression: VariableNode
                name: Variable "$e"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: AssignmentByReferenceNode
        target: VariableNode
          name: Variable "$a"  >Whitespace" "
        equals: '=' "="  >Whitespace" "
        ampersand: AmpersandFollowedByVariableOrVariadic "&"
        expression: VariableNode
          name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: PostfixOpNode
        target: VariableNode
          name: Variable "$a"
        operator: Increment "++"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: PostfixOpNode
        target: VariableNode
          name: Variable "$a"
        operator: Decrement "--"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: PrefixOpNode
        operator: Increment "++"
        target: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: PrefixOpNode
        operator: Decrement "--"
        target: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: UnaryOpNode
        operator: '+' "+"
        expression: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: UnaryOpNode
        operator: '-' "-"
        expression: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: UnaryOpNode
        operator: '!' "!"
        expression: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: UnaryOpNode
        operator: '~' "~"
        expression: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: UnaryOpNode
        operator: '@' "@"
        expression: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: BinaryOpNode
        left: BinaryOpNode
          left: VariableNode
            name: Variable "$a"  >Whitespace" "
          operator: '+' "+"  >Whitespace" "
          right: VariableNode
            name: Variable "$b"  >Whitespace" "
        operator: '-' "-"  >Whitespace" "
        right: BinaryOpNode
          left: BinaryOpNode
            left: BinaryOpNode
              left: VariableNode
                name: Variable "$c"  >Whitespace" "
              operator: '*' "*"  >Whitespace" "
              right: VariableNode
                name: Variable "$d"  >Whitespace" "
            operator: '/' "/"  >Whitespace" "
            right: VariableNode
              name: Variable "$e"  >Whitespace" "
          operator: '%' "%"  >Whitespace" "
          right: BinaryOpNode
            left: VariableNode
              name: Variable "$f"  >Whitespace" "
            operator: Power "**"  >Whitespace" "
            right: VariableNode
              name: Variable "$g"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: BinaryOpNode
        left: VariableNode
          name: Variable "$a"  >Whitespace" "
        operator: '.' "."  >Whitespace" "
        right: VariableNode
          name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: BinaryOpNode
        left: BinaryOpNode
          left: VariableNode
            name: Variable "$a"  >Whitespace" "
          operator: AmpersandFollowedByVariableOrVariadic "&"  >Whitespace" "
          right: VariableNode
            name: Variable "$b"  >Whitespace" "
        operator: '|' "|"  >Whitespace" "
        right: BinaryOpNode
          left: VariableNode
            name: Variable "$c"  >Whitespace" "
          operator: '^' "^"  >Whitespace" "
          right: BinaryOpNode
            left: BinaryOpNode
              left: VariableNode
                name: Variable "$d"  >Whitespace" "
              operator: ShiftLeft "<<"  >Whitespace" "
              right: VariableNode
                name: Variable "$e"  >Whitespace" "
            operator: ShiftRight ">>"  >Whitespace" "
            right: VariableNode
              name: Variable "$f"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: BinaryOpNode
        left: VariableNode
          name: Variable "$a"  >Whitespace" "
        operator: IsEqual "=="  >Whitespace" "
        right: VariableNode
          name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: BinaryOpNode
        left: VariableNode
          name: Variable "$a"  >Whitespace" "
        operator: IsNotEqual "!="  >Whitespace" "
        right: VariableNode
          name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: BinaryOpNode
        left: VariableNode
          name: Variable "$a"  >Whitespace" "
        operator: IsIdentical "==="  >Whitespace" "
        right: VariableNode
          name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: BinaryOpNode
        left: VariableNode
          name: Variable "$a"  >Whitespace" "
        operator: IsNotIdentical "!=="  >Whitespace" "
        right: VariableNode
          name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: BinaryOpNode
        left: VariableNode
          name: Variable "$a"  >Whitespace" "
        operator: '<' "<"  >Whitespace" "
        right: VariableNode
          name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: BinaryOpNode
        left: VariableNode
          name: Variable "$a"  >Whitespace" "
        operator: IsSmallerOrEqual "<="  >Whitespace" "
        right: VariableNode
          name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: BinaryOpNode
        left: VariableNode
          name: Variable "$a"  >Whitespace" "
        operator: '>' ">"  >Whitespace" "
        right: VariableNode
          name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: BinaryOpNode
        left: VariableNode
          name: Variable "$a"  >Whitespace" "
        operator: IsGreaterOrEqual ">="  >Whitespace" "
        right: VariableNode
          name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: BinaryOpNode
        left: VariableNode
          name: Variable "$a"  >Whitespace" "
        operator: Spaceship "<=>"  >Whitespace" "
        right: VariableNode
          name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: BinaryOpNode
        left: BinaryOpNode
          left: BinaryOpNode
            left: BinaryOpNode
              left: VariableNode
                name: Variable "$a"  >Whitespace" "
              operator: BooleanAnd "&&"  >Whitespace" "
              right: VariableNode
                name: Variable "$b"  >Whitespace" "
            operator: BooleanOr "||"  >Whitespace" "
            right: VariableNode
              name: Variable "$c"  >Whitespace" "
          operator: LogicalAnd "and"  >Whitespace" "
          right: VariableNode
            name: Variable "$d"  >Whitespace" "
        operator: LogicalOr "or"  >Whitespace" "
        right: BinaryOpNode
          left: VariableNode
            name: Variable "$e"  >Whitespace" "
          operator: LogicalXor "xor"  >Whitespace" "
          right: VariableNode
            name: Variable "$f"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: TernaryNode
        condition: VariableNode
          name: Variable "$a"  >Whitespace" "
        question: '?' "?"  >Whitespace" "
        then: VariableNode
          name: Variable "$b"  >Whitespace" "
        colon: ':' ":"  >Whitespace" "
        else: VariableNode
          name: Variable "$c"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: TernaryNode
        condition: VariableNode
          name: Variable "$a"  >Whitespace" "
        question: '?' "?"
        colon: ':' ":"  >Whitespace" "
        else: VariableNode
          name: Variable "$c"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: BinaryOpNode
        left: VariableNode
          name: Variable "$a"  >Whitespace" "
        operator: Coalesce "??"  >Whitespace" "
        right: VariableNode
          name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: InstanceofNode
        expression: VariableNode
          name: Variable "$a"  >Whitespace" "
        instanceofKeyword: Instanceof "instanceof"  >Whitespace" "
        class: NameNode
          token: Identifier "B"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: InstanceofNode
        expression: VariableNode
          name: Variable "$a"  >Whitespace" "
        instanceofKeyword: Instanceof "instanceof"  >Whitespace" "
        class: VariableNode
          name: Variable "$b"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: InstanceofNode
        expression: VariableNode
          name: Variable "$a"  >Whitespace" "
        instanceofKeyword: Instanceof "instanceof"  >Whitespace" "
        class: ParenthesizedNode
          openParen: '(' "("
          expression: ConstantFetchNode
            name: NameNode
              token: Identifier "B"
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: BinaryOpNode
        left: VariableNode
          name: Variable "$a"  >Whitespace" "
        operator: Pipe "|>"  >Whitespace" "
        right: FunctionCallNode
          name: NameNode
            token: Identifier "f"
          arguments: ArgumentListNode
            openParen: '(' "("
            items: SeparatedNodeList
              - VariadicPlaceholderNode
                ellipsis: Ellipsis "..."
            closeParen: ')' ")"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: CastNode
        cast: IntCast "(int)"  >Whitespace" "
        expression: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: CastNode
        cast: IntCast "( int )"  >Whitespace" "
        expression: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: CastNode
        cast: FloatCast "(float)"  >Whitespace" "
        expression: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: CastNode
        cast: StringCast "(string)"  >Whitespace" "
        expression: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: CastNode
        cast: ArrayCast "(array)"  >Whitespace" "
        expression: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: CastNode
        cast: ObjectCast "(object)"  >Whitespace" "
        expression: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: CastNode
        cast: BoolCast "(bool)"  >Whitespace" "
        expression: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: CastNode
        cast: VoidCast "(void)"  >Whitespace" "
        expression: FunctionCallNode
          name: NameNode
            token: Identifier "f"
          arguments: ArgumentListNode
            openParen: '(' "("
            items: SeparatedNodeList
            closeParen: ')' ")"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: IssetNode
        issetKeyword: Isset "isset"
        openParen: '(' "("
        variables: SeparatedNodeList
          - VariableNode
            name: Variable "$a"
          - ',' ","  >Whitespace" "
          - VariableNode
            name: Variable "$b"
          - ',' ","
        closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: EmptyNode
        emptyKeyword: Empty "empty"
        openParen: '(' "("
        expression: VariableNode
          name: Variable "$a"
        closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: EvalNode
        evalKeyword: Eval "eval"
        openParen: '(' "("
        expression: StringNode
          token: ConstantEncapsedString "'1'"
        closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: IncludeNode
        includeKeyword: Include "include"  >Whitespace" "
        expression: StringNode
          token: ConstantEncapsedString "'a'"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: IncludeNode
        includeKeyword: IncludeOnce "include_once"  >Whitespace" "
        expression: StringNode
          token: ConstantEncapsedString "'a'"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: IncludeNode
        includeKeyword: Require "require"  >Whitespace" "
        expression: StringNode
          token: ConstantEncapsedString "'a'"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: IncludeNode
        includeKeyword: RequireOnce "require_once"  >Whitespace" "
        expression: StringNode
          token: ConstantEncapsedString "'a'"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: ExitNode
        exitKeyword: Exit "exit"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: ExitNode
        exitKeyword: Exit "exit"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
            - ArgumentNode
              value: IntegerNode
                token: Integer "1"
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: ExitNode
        exitKeyword: Exit "die"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
            - ArgumentNode
              value: StringNode
                token: ConstantEncapsedString "'x'"
          closeParen: ')' ")"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: PrintNode
        printKeyword: Print "print"  >Whitespace" "
        expression: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: ThrowNode
        throwKeyword: Throw "throw"  >Whitespace" "
        expression: NewNode
          newKeyword: New "new"  >Whitespace" "
          class: NameNode
            token: Identifier "E"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: CloneNode
        cloneKeyword: Clone "clone"  >Whitespace" "
        expression: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: FunctionCallNode
        name: NameNode
          token: Clone "clone"
        arguments: ArgumentListNode
          openParen: '(' "("
          items: SeparatedNodeList
            - ArgumentNode
              value: VariableNode
                name: Variable "$a"
            - ',' ","  >Whitespace" "
            - ArgumentNode
              value: ArrayNode
                openDelimiter: '[' "["
                items: SeparatedNodeList
                  - ArrayItemNode
                    value: IntegerNode
                      token: Integer "1"
                closeDelimiter: ']' "]"
          closeParen: ')' ")"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: MatchNode
        matchKeyword: Match "match"  >Whitespace" "
        openParen: '(' "("
        subject: VariableNode
          name: Variable "$a"
        closeParen: ')' ")"  >Whitespace" "
        openBrace: '{' "{"  >Whitespace" "
        arms: SeparatedNodeList
          - MatchArmNode
            values: SeparatedNodeList
              - IntegerNode
                token: Integer "1"
              - ',' ","  >Whitespace" "
              - IntegerNode
                token: Integer "2"  >Whitespace" "
            doubleArrow: DoubleArrow "=>"  >Whitespace" "
            body: StringNode
              token: ConstantEncapsedString "'x'"
          - ',' ","  >Whitespace" "
          - MatchArmNode
            defaultKeyword: Default "default"  >Whitespace" "
            doubleArrow: DoubleArrow "=>"  >Whitespace" "
            body: StringNode
              token: ConstantEncapsedString "'y'"
          - ',' ","  >Whitespace" "
        closeBrace: '}' "}"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: ClosureNode
        attributes: NodeList
        functionKeyword: Function "function"  >Whitespace" "
        openParen: '(' "("
        parameters: SeparatedNodeList
        closeParen: ')' ")"  >Whitespace" "
        body: BlockNode
          openBrace: '{' "{"  >Whitespace" "
          statements: NodeList
          closeBrace: '}' "}"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: ClosureNode
        attributes: NodeList
        staticKeyword: Static "static"  >Whitespace" "
        functionKeyword: Function "function"  >Whitespace" "
        openParen: '(' "("
        parameters: SeparatedNodeList
          - ParameterNode
            attributes: NodeList
            modifiers: ModifiersNode
            ampersand: AmpersandFollowedByVariableOrVariadic "&"
            variable: VariableNode
              name: Variable "$a"
        closeParen: ')' ")"  >Whitespace" "
        uses: ClosureUsesNode
          useKeyword: Use "use"  >Whitespace" "
          openParen: '(' "("
          variables: SeparatedNodeList
            - ClosureUseNode
              variable: VariableNode
                name: Variable "$b"
            - ',' ","  >Whitespace" "
            - ClosureUseNode
              ampersand: AmpersandFollowedByVariableOrVariadic "&"
              variable: VariableNode
                name: Variable "$c"
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
          closeBrace: '}' "}"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: ArrowFunctionNode
        attributes: NodeList
        fnKeyword: Fn "fn"
        openParen: '(' "("
        parameters: SeparatedNodeList
          - ParameterNode
            attributes: NodeList
            modifiers: ModifiersNode
            variable: VariableNode
              name: Variable "$a"
        closeParen: ')' ")"  >Whitespace" "
        doubleArrow: DoubleArrow "=>"  >Whitespace" "
        expression: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: ArrowFunctionNode
        attributes: NodeList
        staticKeyword: Static "static"  >Whitespace" "
        fnKeyword: Fn "fn"
        ampersand: AmpersandNotFollowedByVariableOrVariadic "&"
        openParen: '(' "("
        parameters: SeparatedNodeList
        closeParen: ')' ")"  >Whitespace" "
        doubleArrow: DoubleArrow "=>"  >Whitespace" "
        expression: IntegerNode
          token: Integer "1"
      semicolon: ';' ";"  >EndOfLine"\n"
    - ExpressionStatementNode
      expression: YieldNode
        yieldKeyword: Yield "yield"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: YieldNode
        yieldKeyword: Yield "yield"  >Whitespace" "
        value: VariableNode
          name: Variable "$a"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: YieldNode
        yieldKeyword: Yield "yield"  >Whitespace" "
        key: VariableNode
          name: Variable "$k"  >Whitespace" "
        doubleArrow: DoubleArrow "=>"  >Whitespace" "
        value: VariableNode
          name: Variable "$v"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: YieldFromNode
        yieldFromKeyword: YieldFrom "yield from"  >Whitespace" "
        expression: VariableNode
          name: Variable "$g"
      semicolon: ';' ";"  >Whitespace" "
    - ExpressionStatementNode
      expression: ShellExecNode
        openBacktick: '`' "`"
        parts: NodeList
          - InterpolatedStringPartNode
            token: EncapsedAndWhitespace "ls "
          - VariableNode
            name: Variable "$a"
        closeBacktick: '`' "`"
      semicolon: ';' ";"
  endOfFile: EndOfFile ""
