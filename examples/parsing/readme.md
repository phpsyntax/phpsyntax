# Parsing PHP into a lossless concrete syntax tree (CST) and printing it back byte for byte

Three programs about the contract everything else in this library rests on: parse a file, print the
tree, get the file back. Not "equivalent code", not "the same after normalization". The same bytes.

**You will learn:**

- how to parse PHP source into a tree and print it back with `Parser` and `Printer`
- what the round-trip invariant is and why a refactoring tool needs it
- what a parse error tells you, and what this library deliberately does not do with it
- how to build new nodes by parsing a fragment of code

What a node, a slot, a token and a trivia are is the next chapter. Here they are only the reason the
bytes come back.

```shell
php examples/parsing/round-trip.php
php examples/parsing/errors.php
php examples/parsing/fragments.php
```

## Round trip: parse, print, compare, then change one literal (round-trip.php)

The contract, in three lines:

```php
$parser = new Parser;
$file = $parser->parse($code);
echo Printer::print($file);
```

The input is deliberately horrible: three spaces after `namespace` and one before its semicolon, a
block comment in front of the `function` keyword, a body indented with a mix of tabs, a heredoc, a close
tag with HTML after it. All of it comes back exactly as it went in. Then the script raises the VAT rate by writing one token, and
prints what a reviewer would see:

```
identical
identical again
-   /* block */ function total( array $items,float $vat=0.21){
+   /* block */ function total( array $items,float $vat=0.23){
...
```

Two lines. The heredoc did not move, the odd spacing stayed odd, the close tag and the HTML behind it
are untouched. **That is the whole pitch of a lossless tree, and it is why a codemod is reviewable:**
the diff of a change is the change.

The last part of the script runs the round-trip check over every PHP file of this library's own source,
some hundred and fifty of them, in well under a second. It is the same check the test suite runs over
`tests/corpus/`, 215 files of real and deliberately hostile PHP. Point the loop at your project and you
have verified the parser on your code before you build anything on it; `vendor/bin/phpsyntax check
path/to/project` does the same without the loop.

**Why an abstract syntax tree cannot do this.** An AST keeps what the code *means*. It has no place for
redundant parentheses, for blank lines, for the difference between three spaces and a tab, and comments
hang off the nearest node rather than off the line they were written on. Printing such a tree means
deciding all of that again, and the decisions are the printer's, not yours.
[nikic/php-parser](https://github.com/nikic/PHP-Parser), the parser most PHP tooling is built on, has a
formatting-preserving printer for exactly this reason, and its documentation says what it costs: it
needs the old tokens and the old tree alongside the new one, and it "works on a best-effort basis and
may sometimes reformat more code than necessary".

Here the tree is *concrete*: every token the tokenizer produced is in it, every space and every comment
is trivia attached to a token, and the printer is a concatenation with no logic of its own. Printing is
therefore not a decision at all.

## Parse errors: ParseException with the line and the byte offset (errors.php)

Invalid code is a `ParseException` carrying the position in the three forms a tool needs: the line and the
column for a message, and the byte offset for an editor that jumps to it.

```
Unexpected ';', expecting ')'
line 4, column 25, offset 61
	return array_sum($items;
	                       ^
```

There is no partial tree and no error recovery: the grammar has none, and a formatter, a linter or a
codemod runs over code that already compiles. Say which file was broken and move to the next one. If
you need recovery, for a language server for instance, php-parser has it and this library does not.

## Building nodes from a fragment of code instead of a builder API (fragments.php)

New nodes are made by parsing their text. Four shortcuts cover what you need most of the time, and
`parseFragment()` covers the items of lists by naming the class you want, a member, a parameter, an
argument, an array item, a match arm, a `catch`, an import and more:

```php
$expr = $parser->parseExpression('$price * (1 + $vat)');
$stmt = $parser->parseStatement('if ($qty > 10) { $price *= 0.9; }');
$type = $parser->parseType('int|string|null');
$name = $parser->parseName('\Shop\Cart');
$method = $parser->parseFragment(MemberNode::class, 'public function total(): float {}');
$parameter = $parser->parseFragment(ParameterNode::class, "private string \$currency = 'EUR'");
```

```
PhpSyntax\Nodes\Expression\BinaryOpNode: $price * (1 + $vat)
PhpSyntax\Nodes\Statement\IfNode: if ($qty > 10) { $price *= 0.9; }
PhpSyntax\Nodes\Type\UnionTypeNode: int|string|null
PhpSyntax\Nodes\NameNode: \Shop\Cart
PhpSyntax\Nodes\Member\MethodNode: public function total(): float {}
PhpSyntax\Nodes\ParameterNode: private string $currency = 'EUR'
parent: NULL
printed: "return 1;"

- $total = $price   *   $qty;   // keep this comment
+ $total = $price * (1 + $vat);   // keep this comment
```

Every one of them returns a **detached** node: no parent, no file, no original positions, and no
whitespace on its outer edges, which is why the padded `  return 1;  ` prints without its padding. The
last two lines show what that is for: the fragment is written into a slot of a real tree, and the
comment after the statement keeps its place and its three spaces. That whitespace belongs to the `;`,
not to the expression that was replaced, so the spacing inside the old expression went with it and the
spacing after the statement did not.

`parseFragment()` matters more than it looks, because the things a codemod inserts are usually list
items: a method into a class body, a parameter into a signature, an item into an array. Ask for the
class and you get that node, already detached and with clean edges, instead of parsing a helper class
around it and lifting the piece out.

The constructors of the nodes are `@internal`, and for a good reason: `IfNode` takes eleven slots, the
keyword, both parentheses and the `colon`, `statements` and `endKeyword` of the alternative syntax among
them, and a PHP release may add one. The text of a node is shorter, it is the language you already
write, and it cannot fall out of step with the grammar. What a string cannot carry, the nodes you
already hold, the `of()` factories put together, see [mutation/](../mutation).

## Try it yourself

1. Run the round trip over your own project, either by changing the directory in the last loop of
   `round-trip.php` or with `vendor/bin/phpsyntax check path/to/project`. Anything that fails is a bug
   worth reporting.
2. In `errors.php`, delete the closing brace of the function instead and read what the parser says.
3. Ask `parseExpression()` for `match ($a) { 1 => 'one' }` and print the class you get back.

## Further reading

- [The tree](../tree) - nodes, slots, tokens and trivia, the next chapter
- [Nodes reference](../../docs/reference/nodes.md) - every node class with its slots
- [internals](../../docs/internals.md) - the trivia rules and the round-trip invariant
