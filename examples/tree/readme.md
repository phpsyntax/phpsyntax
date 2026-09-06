# The concrete syntax tree (CST) of PHP: nodes, named slots, tokens and trivia

Six programs about what you actually hold after parsing, and the vocabulary the rest of the library
speaks.

**You will learn:**

- how a node, its named slots, its tokens and their trivia fit together
- how to search the tree downwards by class or predicate, and upwards to an ancestor
- what a name knows about itself before anything resolves it
- why a literal carries its notation as well as its value
- why the class of a node follows what the code means and not how it is spelled
- which argument a parameter gets when the call is written by name, unpacked or not bound at all

```shell
php examples/tree/inspect.php
php examples/tree/find.php
php examples/tree/names.php
php examples/tree/literals.php
php examples/tree/destructuring.php
php examples/tree/arguments.php
```

## Reading the tree of a PHP file: nodes, slots and tokens (inspect.php)

Five lines of PHP, printed as the tree that represents them.

```
PhpSyntax\Nodes\FileNode
  statements: PhpSyntax\Nodes\NodeList
    - PhpSyntax\Nodes\Statement\IfNode
      ifKeyword: Token 'if'  leading: OpenTag("<?php\n") Comment("// a discount that applies from ten pieces") EndOfLine("\n")  trailing: Whitespace(" ")
      openParen: Token '('
      condition: PhpSyntax\Nodes\Expression\BinaryOpNode
        left: PhpSyntax\Nodes\Expression\VariableNode
          name: Token '$qty'  trailing: Whitespace(" ")
        operator: Token '>='  trailing: Whitespace(" ")
        right: PhpSyntax\Nodes\Scalar\IntegerNode
          token: Token '10'
...
      body: PhpSyntax\Nodes\Statement\BlockNode
        openBrace: Token '{'  trailing: EndOfLine("\n")
...
      elseifs: PhpSyntax\Nodes\NodeList
  endOfFile: Token ''
```

Four things to take from that listing.

**Every token is in the tree.** `if`, `(`, `)`, `{`, `;`, `}` are not implied by the node class, they
are children of it. That is what *concrete* means, and it is why printing the tree cannot lose them.

**Children have names, not numbers.** `IfNode` has `ifKeyword`, `openParen`, `condition`, `closeParen`,
`body`, and where the alternative syntax is used, `colon`, `statements`, `endKeyword`, `semicolon`. You
write `$if->condition`, never `$children[2]`. The names are listed for every class in the
[nodes reference](../../docs/reference/nodes.md), and a node also carries them in its `Slots` constant,
which is how the dump above walks a tree it knows nothing about.

**Whitespace and comments are trivia on tokens.** The rule is worth memorizing: *what follows a token
up to and including the end of its line is that token's trailing trivia; everything else is the leading
trivia of the next token.* So the comment above `if` and the line ending after it are leading trivia of
`if`, and the single space after `>=` is trailing trivia of `>=`. Not a property of the expression, not
a floating "comment attached to a statement" as in an abstract syntax tree, but text belonging to a
token. Which is exactly why it survives a rewrite of the code around it.

**`<?php` is not a token.** It is `OpenTag` trivia in front of the first token, and it carries its own
whitespace. The mirror image is `?>`, which *is* a token, and which keeps the newline PHP swallows
after it. Both are trivia rules you can forget about until the day you write a fixer that would
otherwise eat the opening tag; then you will be glad they are rules and not accidents.

The empty `elseifs` list at the end is not a mistake: a list slot always holds a list, so
`$if->elseifs` is never `null`. An optional slot is a different matter and really is `null` when the
code does not have it, which is why the dump shows no `else` and no `endKeyword`.

## Searching a PHP syntax tree: find() by node class, predicate and ancestor (find.php)

Searching downwards, by class and by a predicate that narrows it:

```php
foreach ($file->find(StringNode::class) as $string) { ... }
$long = $file->find(StringNode::class, fn(StringNode $string) => strlen($string->value) > 8);
$call = $file->findFirst(FunctionCallNode::class, ...);
$class = $call->findAncestor(ClassNode::class);
```

```
string on line 6: <table id="%s">
string on line 8: <tr><td>
string on line 8: label
string on line 8: </td></tr>
string on line 10: </table>

call: sprintf() with 2 argument(s)
call: htmlspecialchars() with 1 argument(s)

2 string literals are longer than 8 characters

htmlspecialchars() is called in Invoice::render()
```

`find()` returns a plain array, in source order, and it is a **snapshot**: you may rewrite or delete
the nodes you got while you iterate them, which is what a codemod does all day. The class comes first
and the predicate narrows it, so the result stays typed either way: `$call->arguments` is offered by the IDE
and checked by PHPStan, and the closure receives the node already typed. A filter may also be an
interface, which is how `find(ClassLikeNode::class)` collects classes, interfaces, traits and enums in
one go.

Upwards there is `findAncestor()`, and it is the answer to "which method is this call in", a question
every code-quality rule asks. Note that `find()` skips tokens: it walks nodes only. To move from token
to token, the positions chapter has `getNext()` and `getPrevious()`.

## PHP class, function and constant names: qualified, fully qualified, keyword (names.php)

A name is one node holding one token, whatever its form, and it answers questions about itself.

```
written                  kind           role       short name
Shop\Billing             Qualified      ClassLike  Billing
Shop\Money               Qualified      ClassLike  Money
Money                    Unqualified    ClassLike  Money
\DateTimeImmutable       FullyQualified ClassLike  DateTimeImmutable
string                   Unqualified    ClassLike  string
\sprintf                 FullyQualified Function   sprintf
DATE_ATOM                Unqualified    Constant   DATE_ATOM

static is a name written as a keyword: true
the call is written \sprintf
  equals('\SPRINTF'): true
  equals('sprintf'):   false
```

`$text` is what stands in the file, `$kind` how it is written, `$parts` and `$shortName` take it apart,
and `$role` says what it stands for, derived from its place in the tree: a function when it is being
called, a constant when it is fetched, a class otherwise. That last one is why a tool can ask "is this
a class name?" without pattern-matching on the parent node.

`equals()` asks whether the name is **written** the same way. It ignores letter case where PHP ignores
it, and it compares the leading backslash: `\sprintf` equals `\SPRINTF` and does not equal `sprintf`.
That is the right question for a rule about how code is spelled, and the wrong one for "does this refer
to the same thing", which is what `NameResolver` answers in the [analyses chapter](../analyses).

Three caveats the table shows. The built-in type `string` is reported as `ClassLike`, because by its
place in the tree that is what it is; `NamedTypeNode::isBuiltin()` is the question you meant to ask.
`static` is a name written as a keyword token, which `isKeyword()` tells you. And a name inside a `use`
or `namespace` statement carries the role of what it imports, `ClassLike` for a plain import, because
PHP keeps classes and namespaces in one table and `use Foo;` introduces both at once; `isDeclaration()`
tells such a name from one that refers to something.

Resolving a name to what it actually refers to, imports and namespace and all, is a job for
`NameResolver` in the [analyses chapter](../analyses).

## PHP literals keep their notation: 0x1F, quote style, heredoc (literals.php)

Every literal keeps its text *and* tells you the value it stands for.

```
literal  as written   detail   value
integer  0x1F         base 16  31
integer  0b1010       base 2   10
integer  0o17         base 8   15
integer  017          base 8   15
integer  1_000_000    base 10  1000000
float    1.5e-3                0.0015
string   'O\'Brien'   quote '  O'Brien
string   "C:\\temp"   quote "  C:\temp
heredoc  TXT          indent "\t" "indented body"
heredoc  SQL          indent "\t" (interpolated, no single value)
nowdoc   RE           indent "\t" "\\d+\\s*$total"

written      node                     kind
true         BooleanNode              literal
FALSE        BooleanNode              literal
\null        NullNode                 literal
PHP_EOL      ConstantFetchNode        name to resolve
__LINE__     MagicConstantNode        literal

['id' => 7, 'tags' => ['a', 'b'], 'live' => true] {"id":7,"tags":["a","b"],"live":true}
"$prefix-1"                                  written as no value
PHP_INT_MAX                                  written as no value
1 + 2                                        written as no value
```

This is the pair an abstract syntax tree cannot give you. There, `0x1F` and `31` are the same node
holding the integer 31, and printing it back is a guess. Here `$token->text` is `0x1F`, `$base` is 16
and `$value` is 31, so a rule about hexadecimal notation is three lines and cannot accidentally rewrite
decimals.

The same for strings: `$quote` is the delimiter, `$value` is the content with escapes resolved, and the
raw text is still there. A rule that converts double quotes to single ones where nothing is
interpolated only has to ask for `setValue($value, "'")`, and the escaping is done correctly, by the
library, in the direction of the new delimiter.

A heredoc knows its `$label`, its closing `$indentation` and, when nothing is interpolated, its
`$value`. The second heredoc in the run contains `{$id}`, so it has no single string value and
`hasInterpolation()` says so; reading `$value` there raises an exception rather than handing you a
half-truth. The third is a nowdoc, which `isNowdoc()` tells apart: it holds `$total` and `\d`, and
neither is interpolated or escaped, so its `$value` is the text as written.

`true`, `false` and `null` are literals of their own, in the letter case and with the leading backslash
they were written with. An abstract syntax tree usually reads them as constant fetches, which leaves a
rule about constants having to except three names by hand and a rule about booleans having to look for
them among names. Here `BooleanNode` and `NullNode` say it, and `ScalarNode` covers every literal at
once, so the only thing left in that column that still needs resolving against imports is a real name.

The last block scales that up from a literal to a whole expression. `toValue()` reads an expression
that **is written as a value**, arrays and nesting included, and `hasValue()` asks first. Note what it
refuses: an interpolated string, `PHP_INT_MAX`, `1 + 2`. All three are constant to PHP, and none of
them is written as a value; what a constant name means depends on code elsewhere, and this is a syntax
layer. A rule that reads a configuration array or an attribute argument gets what it needs, and a rule
that would have to evaluate gets an honest no.

## One node for one meaning: destructuring however it is written (destructuring.php)

PHP writes destructuring two ways. Both mean the same thing, and both are a `ListNode`.

```
written                    node         keyword    nested item
[$a, $b] = $row            ListNode     null       VariableNode
list($a, $b) = $row        ListNode     "list"     VariableNode
[$a, [$b, $c]] = $row      ListNode     null       ListNode
list($a, [$b, $c]) = $row  ListNode     "list"     ListNode
$row = [$a, $b]            ArrayNode

list($a, [$b]) = $row      prints back as list($a, [$b]) = $row
[$a, list($b)] = $row      prints back as [$a, list($b)] = $row
```

The class answers what the code **means**: a rule about destructuring asks for `ListNode` and gets both
spellings, nested ones included, whichever way round they nest. The spelling is not lost, it moved into
a slot: `$listKeyword` is the `list` token or null, and the delimiters are the brackets or the
parentheses that were written, so the text prints back byte for byte.

The last row is the other half of the deal. The same square brackets on the right of an assignment are
an array literal and stay an `ArrayNode`, because there they mean a value. One question, one answer:
`ArrayNode` is a literal, `ListNode` is a target, and neither of them is sometimes the other.

## Which argument a parameter gets, however the call is written (arguments.php)

A rule about `str_replace()` cares about its third parameter, not about the third comma.

```
call                                                   closure of it  subject argument
str_replace('a', 'b', $text)                           false          $text
str_replace(subject: $text, search: 'a', replace: 'b') false          $text
str_replace('a', 'b', subject: $text)                  false          $text
str_replace(...$args)                                  false          -
str_replace(...)                                       true           -
str_replace('a', ?, $text)                             true           -

written    plainName
$total     "total"
$$name     null
$name      "name"
${$key}    null
$key       "key"
```

`findArgument('subject', 2)` takes the name of the parameter and its position and returns whichever
argument feeds it: the one written with that name wherever it stands, otherwise the one in that
position. Write the same rule by counting commas and it breaks on the second call in the run, which is
a shape real code takes as soon as a function has more than three parameters.

The fourth call is the one worth pausing on. `...$args` unpacks an array whose length is not known
until the code runs, so nothing after it has a position any more and the answer is an honest `-`
rather than a guess at the argument that happens to be written third.

The last two calls bind no parameters at all. `f(...)` makes a first-class callable and `f('a', ?, $c)`
a partial application, and neither of them is a call being made, so asking which argument a parameter
gets is the wrong question. `isPartialApplication()` is how you ask before you ask.

The second block is the same idea one level down. `$plainName` is the name of a variable without the
dollar, which is what compares to a parameter name in a phpDoc or to a promoted property. A variable
whose name is itself an expression has no such name until the code runs, and answers `null` instead of
handing you the text of the expression.

## Try it yourself

1. Point `inspect.php` at a `match` expression or a class with attributes and read the slot names.
2. In `find.php`, list every method of the class with its line: `find(MethodNode::class)` plus
   `getStartLine()`.
3. In `literals.php`, add `0.1 + 0.2` and check that both floats keep their text while `$value` gives
   you the usual surprise.
4. In `destructuring.php`, add `foreach ($rows as [$a, $b]) {}` and confirm the value of the foreach is
   a `ListNode` too.

## Further reading

- [Nodes reference](../../docs/reference/nodes.md) - every node class with its slots
- [trivia/](../trivia) - comments and whitespace in detail
- [analyses/](../analyses) - resolving names against imports and namespaces
