# Rewriting PHP code: a codemod whose diff is only what you changed

This is what the library is for. Four programs that change code the way a refactoring tool has to: the
formatting of everything you did not touch stays as its author wrote it, so the diff a reviewer opens
contains your change and nothing else.

**You will learn:**

- how to write text, slots and whole subtrees, and which level of the API to use for what
- how a list keeps its separators, its indentation and its trailing comma
- which questions to ask before a rewrite so it stays correct
- why a tool built this way produces diffs a human will actually approve

```shell
php examples/mutation/text.php
php examples/mutation/replace.php
php examples/mutation/lists.php
php examples/mutation/safety.php
```

The rule behind all of it: **a slot is written by assignment** (`$if->condition = $expression`), and the write
moves the parents and tells the token index. Text and trivia of a token are written by methods
(`setText()`, `setLeadingTrivia()`, `setTrailingSpace()`), because `?->` cannot stand on the left of an
assignment. Anything the language can guard, it guards: the items of a list are `protected(set)` and
change only through the list's own methods.

## Changing what a token says: literals, names, identifiers (text.php)

```
- use Legacy\Mailer;
+ use App\Mail\Mailer;
- 	public function notify(Mailer $mailer): void
+ 	public function notifyCustomer(Mailer $mailer): void
- 		$mailer->send("O'Brien <ob@example.com>", 0x1F);
+ 		$mailer->send('Anna "Nan" Kral <ak@example.com>', 0b11111);

InvalidArgumentException: 'notify customer' is not an identifier.
```

Four writes, and each one gets a different amount of help from the library.

`StringNode::setValue($value, "'")` takes the value and the delimiter **together**, because the
delimiter decides how the value is escaped. The new value contains double quotes, the requested
delimiter is a single quote, and the escaping comes out right without you thinking about it. That is
also why it is a method and not two properties: two properties would mean writing one reads the other,
and the caller would have to think about the order.

`NameNode::$text` re-tokenizes what you write, so a qualified name does not stay an unqualified token
and the printer cannot produce something the parser would not accept. `IdentifierNode::$text` refuses
anything that is not an identifier, which is the exception at the end of the run: a space in a method
name would otherwise end up inside a token, and the printer would faithfully write it out.

`Token::setText()` is the level below, and it is the escape hatch for the cases the nodes do not model,
here rewriting a hexadecimal literal as a binary one.

## Replacing and removing nodes without disturbing the neighbours (replace.php)

```
- 	$legacy = compat_normalize($order);
- 	if (count($order['items']) === 0) {   // nothing to ship
+ 	if ($order['items'] === []) {   // nothing to ship
- 	return implode(',', array_map('strval', $order['items']));
+ 	return implode(',', $order['items']);
```

The point of that diff is what is not in it.

**Writing a slot** (`$if->condition = $parser->parseExpression(...)`) puts a new node where the old one was.
The comment sitting after the opening brace, with its three spaces of alignment, is trivia of a token
that nobody touched, so it is exactly where its author left it.

**`replaceWith()`** does the same from the child's side and carries the trivia of the old node onto the
new one, which is what you want when the node you are replacing is a whole subexpression. A node cloned
from elsewhere in the same file arrives with the trivia of *its* old place, and for the first statement
of a file that includes the `<?php` tag, so clear its edges (`setEdgeTrivia([], [])`) before inserting
it. A fragment from the parser has no edges to clear, which is why these examples reach for one.

**`remove()`** takes a list item out. A node alone on its lines takes the lines with it; a node sharing
a line leaves the spacing around it alone. And the comment above the removed statement is not
collateral damage: `CommentPolicy` decides whether it moves to the next token, to the previous one, or
goes. Deleting code and keeping the note somebody left about it is the difference between a tool people
trust and a tool people run once.

A note on how this compares. php-parser can preserve formatting too, through a printer added for
exactly this purpose, and it needs the old tree, the old tokens and a cloned new tree to do it, on a
best-effort basis. Here there is no second tree and no effort: the comment aligned three spaces after
the brace did not survive the rewrite, it was never involved in it. It belongs to a token nobody
touched.

## Lists: separators, indentation and the trailing comma (lists.php)

```
one item inserted into a multi-line array:
+ 	'port' => 3306,

an argument appended and an item removed:
- 	'host' => 'localhost',
- $connection = connect('mysql', 'localhost');
+ $connection = connect('mysql', 'localhost', 'utf8mb4');

an import inserted between two statements:
+ use App\Currency;
```

Adding an item to a list is where hand-written tools produce their ugliest output: a comma too many, an
item at column zero, a trailing comma in a one-line call. `SeparatedNodeList::insert()` models the list
it is inserting into. The separator is cloned from the separators already there, so a multi-line list
gets `,\n` and a one-line list gets `, `. The new item takes the indentation of its neighbour. The
trailing comma stays the trailing comma.

`removeItem()` takes the separator that went with the item: the one after it, or for the last item the
one before it, which is how the trailing comma survives a removal at the end of a list.

A `NodeList` of statements has no separator to carry the line ending, so the inserted statement takes one
of its own, the way its neighbour ends its line; without that it would borrow the blank line that
followed the imports, and the diff would say two things instead of one.

An item of a list is a fragment like any other: `parseFragment(ArrayItemNode::class, "'port' => 3306")`
gives it detached, with nothing of the code it was parsed in on its edges. Inserting a node that already
has a parent is a `LogicException` rather than a tree with two owners; the one exception is a node taken
from inside what the write releases, which is how an operand is lifted up in place of what held it.

## Safe refactoring checks before a codemod rewrites PHP code (safety.php)

```
assignment                             same   repeatable   comment  verdict
$total = $total + $vat                 true   true         false    rewrite
$cart['sum'] = $cart['sum'] + $vat     true   true         false    rewrite
$counts[$i++] = $counts[$i++] + 1      true   false        false    leave alone
$total = $subtotal + $vat              false  true         false    leave alone
$net = $net /* without VAT */ + $fee   true   true         true     leave alone (comment)

parentheses      redundant    reached by   why
($user->name)    true         -            nothing around them binds tighter
(new DateTime)   false        Member       the parent reaches inside them
($factory)       true         Call         nothing around them binds tighter
(FOO)            false        ClassName    the parent reaches inside them
($a + $b)        false        -            what stands around them binds at least as tightly
($a + $b)        true         -            nothing around them binds tighter
```

`$a = $a + $b` becomes `$a += $b`. That innocent rule needs three questions answered, and the table is
the answer to each.

**`matches()`** compares two nodes by the text of their tokens, so the whitespace between them does not
matter and `$cart['sum']` on both sides counts as the same expression.

**`isRepeatableRead()`** asks whether reading the target a second time is free of side effects.
`$counts[$i++]` is textually identical on both sides and increments `$i` on each read, so rewriting it
would change what the program does. That is the trap this rule is famous for.

**`hasComment()`** is the third question, and it is the one that gets forgotten, because `matches()`
compares tokens and knows nothing about the comment between them. Rewriting the last line would delete
`/* without VAT */`, and a tool that silently eats comments is a tool people stop running.

The second table answers a question of the same shape, and look at the last two rows: the **same**
expression `($a + $b)`, once inside `($a + $b) * $c` and once alone in `$all = ($a + $b);`. The
parentheses are load-bearing in the first and noise in the second, and `isRedundant()` says so, because
it weighs how tightly what stands around them binds against what stands inside them.

Two things make that answer trustworthy. Every node written with an operator implements `OperatorNode`
and states its own precedence and associativity, so the table of binding lives in the nodes rather than
in a rule that has to be kept in sync with PHP. And where the composition is not clear, `isRedundant()`
answers `false`: a codemod that keeps a redundant pair of parentheses is dull, one that removes a
necessary pair is a bug report.

Underneath sits the narrower question of how the parent reaches in at all, and `getAccessKind()`
answers it: `Member` for `->` and `[]`, `Call` for `(...)`, `ClassName` for `::`, and `null` where
nothing reaches in; `isDereferenced()` is the same question asked without the kind. The kind is not
decoration. `($factory)()` may lose its parentheses, because a variable can be called bare, while
`(FOO)::class` may not: without them the name itself would be the class, instead of the class of
whatever the constant holds.

## Writing the result back

The tree is the whole state, so saving is what you expect:

```php
if ($file->revision > 0) {                 // nothing was changed otherwise
	file_put_contents($path, (string) $file);
}
```

One thing to know when a script does several passes: an analysis such as `NameResolver` or `Scope`
reads the tree as it was when it was built, so after a mutation you build a new one. `$file->revision`
is what tells you a mutation happened; it counts writes rather than your calls, so compare it, never
count on it.

## Try it yourself

1. In `text.php`, write `$name->text = 'Mailer'` and confirm the token kind changes with the name.
2. In `lists.php`, insert the array item at index 0 and watch the indentation follow the first item.
3. Write the `+=` rewrite for real: when `safety.php` says "rewrite", set the assignment operator to
   `+=` and give the assignment the right-hand operand. The operand goes over as it is: what the write
   releases is where it comes from.

## Further reading

- [trivia/](../trivia) - the whitespace helpers these rewrites build on
- [positions/](../positions) - the index that follows a mutation
- [internals](../../docs/internals.md) - the mutation protocol in full
