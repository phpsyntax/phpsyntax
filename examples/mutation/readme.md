# Rewriting PHP code: a codemod whose diff is only what you changed

This is what the library is for. Eleven programs that change code the way a refactoring tool has to: the
formatting of everything you did not touch stays as its author wrote it, so the diff a reviewer opens
contains your change and nothing else.

**You will learn:**

- how to write text, slots and whole subtrees, and which level of the API to use for what
- how to add and remove modifiers and types without losing the comments around them
- how to write an expression into a place that has operators around it, or into a string, without
  changing what the code means
- how to build new code from a template, and from data with the methods of the builder
- how a list keeps its separators, its indentation and its trailing comma
- how statements move: a block unwrapped, a line removed with its blank line, a neighbor inserted
- which questions to ask before a rewrite so it stays correct
- why a tool built this way produces diffs a human will actually approve

```shell
php examples/mutation/text.php
php examples/mutation/modifiers.php
php examples/mutation/signatures.php
php examples/mutation/replace.php
php examples/mutation/expressions.php
php examples/mutation/strings.php
php examples/mutation/builder.php
php examples/mutation/building.php
php examples/mutation/lists.php
php examples/mutation/statements.php
php examples/mutation/safety.php
```

The rule behind all of it: **a slot is written by assignment** (`$if->condition = $expression`), and the
write moves the parents and tells the token index. Text and trivia of a token are written by methods
(`setText()`, `setLeadingTrivia()`, `setTrailingSpace()`), because `?->` cannot stand on the left of an
assignment; each returns what it wrote to, so `$token->setText('0x1F')->setTrailingSpace(' ')` reads as
one step. A slot that comes with a token of its own, a return type with its colon, is written by a method
too (`setReturnType()`), because two things change together. Anything the language can guard, it guards:
the items of a list are the list's own and change only through its methods.

## Changing what a token says: literals, names, identifiers (text.php)

```
- use Legacy\Mailer;
+ use App\Mail\Mailer;
- 	public function notify(Mailer $mailer): void
+ 	public function notifyCustomer(Mailer $mailer): void
- 		$mailer->send("O'Brien <ob@example.com>", 0x1F);
+ 		$mailer->send('Anna "Nan" Kral <ak@example.com>', 0b11111);

InvalidArgumentException: `notify customer` is not an identifier.
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

## Adding and removing modifiers: final, static, public (modifiers.php)

```
- class Mailer
+ final class Mailer
- 	public static /* kept for the old client */ function send(): void {}
+ 	public /* kept for the old client */ function send(): void {}
- 	function queue(): void {}
+ 	public function queue(): void {}
```

Three one-line diffs, and look at what is not in them. The doc comment above `class Mailer` and the one
above `queue()` did not move, the `<?php` on the first line did not move either, and the comment that
followed `static` is still in the code.

Modifiers are not slots but tokens of a **`ModifiersNode`**, in source order, and a keyword is a token
made from its text, `Token::fromText('final')`, the lexer giving it its kind. **`append()`** knows that
the first modifier opens the declaration, so it takes over the trivia that stood in front of `class` or
`function`, the doc comment, the indentation and, for the first class of a file, the open tag; a token
without trailing whitespace gets a space after it. **`removeToken()`** does the reverse: the leading
trivia go to the token after it, and a comment after the keyword stays in the code. `has()`,
`findToken()`, `getVisibilityToken()` and `$static` and its kin are the questions to ask first.

## Adding, replacing and removing type declarations (signatures.php)

```
- 	public $items = [];
- 	private /* cached */ $total;
+ 	public array $items = [];
+ 	private /* cached */ float $total;
- 	public function __construct(array $items = []) : void
+ 	public function __construct(array $items = [])
- 	public function add($item, mixed $note = null)
+ 	public function add(Item $item, $note = null): void
```

Six types written, and each comes with something the reader of the diff would notice if it went wrong:
the colon of a return type, the space after the type of a parameter, the comment between `private` and
the property.

A return type is not one slot but two, the colon and the type, so it is written by a method that changes
both: **`setReturnType()`** writes `: void` right after the closing parenthesis, and with `null` it removes
the colon, the type and the space before the colon, which is how the constructor loses the return type
PHP refuses there. The line break before the body is not part of it, so the brace stays on the line below.
**`ParameterNode::setType()`** and **`PropertyNode::setType()`** write the type with one space after it and
remove it with that space; `mixed` on a parameter says nothing a missing type does not, so it goes. The
comment after `private` stays where it was, and the type comes after it, right before the variable it
types.

The type of `$total` is the return type of `getTotal()`, handed over as it stands in the file. The setter
takes a copy, as the builder does, so the method keeps its own and the comment after it, and nothing of
the place the type came from travels with it.

## Replacing and removing nodes without disturbing the neighbors (replace.php)

```
- 	// TODO: drop this once the old client is gone
- 	$legacy = compat_normalize($order);
-
- 	if (count($order['items']) === 0) {   // nothing to ship
+ 	if ($order['items'] === []) {   // nothing to ship
- 	return implode(',', array_map('strval', $order['items']));
+ 	return implode(',', $order['items']);
```

The point of that diff is what is not in it.

**Writing a slot** (`$if->condition = $builder->expression(...)`) puts a new node where the old one was.
The comment sitting after the opening brace, with its three spaces of alignment, is trivia of a token
that nobody touched, so it is exactly where its author left it.

**`replaceWith()`** does the same from the child's side and carries the trivia of the old node onto the
new one, which is what you want when the node you are replacing is a whole subexpression. A node copied
from elsewhere in the same file arrives with the trivia of *its* old place, and for the first statement
of a file that includes the `<?php` tag, so take the copy with `withoutEdgeTrivia()` rather than with
`clone`. A fragment from the parser has no edges to clear, which is why these examples reach for one.

**`remove()`** takes a list item out. A node alone on its lines takes the lines with it; a node sharing
a line leaves the spacing around it alone. The blank line below it would stay too, and the body would
open with one; **`mergeBlankLines: true`** keeps the narrower of the two gaps, and for the first
statement of a body that is the gap after the brace, none. And the comment above the removed statement is
not collateral damage: **`CommentPolicy`** decides whether it moves to the next token, to the previous
one, or goes. Here the note is about the line that goes, so the script passes `CommentPolicy::Drop`;
left to the default, `MoveToNextToken`, it would stay above the next statement. A comment is never lost
because a node was, it is lost because you said so, and that is the difference between a tool people
trust and a tool people run once.

A note on how this compares. [nikic/PHP-Parser](https://github.com/nikic/PHP-Parser) can preserve
formatting too, through a printer added for exactly this purpose, and it needs the old tree, the old
tokens and a cloned new tree to do it, on a best-effort basis. Here there is no second tree and no
effort: the comment aligned three spaces after the brace did not survive the rewrite, it was never
involved in it. It belongs to a token nobody touched.

## Writing an expression where operators stand around it (expressions.php)

```
replaceWith()
- 	$name = legacy_str($row['name'] ?? 'anonymous');
+ 	$name = $row['name'] ?? 'anonymous';
- 	$title = legacy_str($row['title'] ?? '') . ' - ' . $name;
+ 	$title = $row['title'] ?? '' . ' - ' . $name;
- 	$tag = 'v'.legacy_str(119);
+ 	$tag = 'v'. 119;

replaceWithExpression()
- 	$name = legacy_str($row['name'] ?? 'anonymous');
+ 	$name = $row['name'] ?? 'anonymous';
- 	$title = legacy_str($row['title'] ?? '') . ' - ' . $name;
+ 	$title = ($row['title'] ?? '') . ' - ' . $name;
- 	$tag = 'v'.legacy_str(119);
+ 	$tag = 'v'. 119;
...
left     right   together   written as
.        119     no         . 119
.        'x'     yes        .'x'
-        -       no         - -
return   FOO     no         return FOO
]        [       yes        ][
```

The codemod drops a wrapper function and keeps its argument, which is about as simple as a rewrite gets.
Read the middle line of the first diff: the argument came out exactly right and the statement now means
something else. `??` binds looser than `.`, so `$row['title'] ?? '' . ' - ' . $name` is
`$row['title'] ?? ('' . ' - ' . $name)`. The file parses, the tests on the happy path pass, and the bug
ships.

**`replaceWithExpression()`** is `replaceWith()` for exactly that case. It writes the expression in
parentheses and takes them away again wherever `isRedundant()` calls them needless, so the first line
loses them and the second keeps them, and the precedence rule is nowhere in the rewrite.

Which of the two to reach for: `replaceWith()` where the place is fenced by delimiters, an argument, a
match arm, the operand of a `return`. `replaceWithExpression()` wherever the place is an operand of an
operator or is reached into by `->`, `[]`, `()` or `::`. It answers about the place the node stands in,
so it works in a detached fragment too, which is where a template of an operator holds its operand.

The last line of both diffs is a hazard of another kind, and `replaceWith()` handles it alone.
`'v'.legacy_str(119)` has no space around the `.`, and `.119` is not a dot followed by a number, it is
the number `0.119`. So after the swap `replaceWith()` asks the lexer about both seams it has just made
and puts a space where the pair would be read as one thing. The table is that same question asked
directly: **`Lexer::canAdjoin()`**, which is what you call when you are the one taking whitespace away.
A formatter that squeezes `- -$a` into `--$a` has written a decrement, and what comes out still compiles.

## Rewriting variables inside strings, heredocs and shell commands (strings.php)

```
- $greeting = "Dear $name, you owe $total EUR.";
+ $greeting = "Dear $customer->name, you owe {$order->getTotal()} EUR.";
- 	Order $id for $city
+ 	Order {$order["id"]} for {$customer->address->city}
- $usage = `du -sh $dir`;
+ $usage = `du -sh $this->root`;

$a          true
$a->b()     true
$a::$b      true
A::$b       false
count($a)   false
$a * 2      false

InvalidArgumentException: Expression `$total * $rate` cannot be written inside a string, which takes a variable, an element, a property or a call reached from a variable.
```

The codemod is one loop that does not know it is working inside strings, and look at what each place
got. `$customer->name` and `$this->root` stand bare, because PHP reads `$a->b` in a string as a property.
The other three got braces, each for its own reason: a string reads `$order->getTotal()` as the property
`getTotal` followed by the text `()`, `$order["id"]` bare would be no code at all, and
`$customer->address->city` bare is `$customer->address` followed by the text `->city`. Each of those would
have been a string that still parses and prints something else.

**`replaceWithExpression()`** is the same call as outside a string. There it decides about parentheses,
here it answers the grammar of interpolation: bare where the bare form reads the expression the same,
`{$...}` where it does not, in a double-quoted string, a heredoc and a shell command alike. The heredoc
keeps its indentation, which is part of its value.

Not everything can stand in a string. After `{$` the grammar reads only what starts with the dollar of a
variable: a variable, an element, a property, a static property or a call reached from one. That is what
**`canStandInString()`** answers, so `$a::$b` may stand there and `A::$b` may not, and neither may a
function call or an arithmetic. A place in a string refuses anything else before it changes a thing, which
is the exception at the end; a codemod that meets such an expression asks first and writes a
concatenation instead.

## Building code from a template with placeholders (builder.php)

```
built:            ($cart['items'] + $cart['shipping']) * (1 + $rate)
the sum is still: $cart['items'] + $cart['shipping']

- 	$net = $cart['items'] + $cart['shipping'];   // shipping is taxed too
- 	$label = isset($cart['label']) ? $cart['label'] : 'Invoice';
+ 	$net = ($cart['items'] + $cart['shipping']) * (1 + $rate);   // shipping is taxed too
+ 	$label = $cart['label'] ?? 'Invoice';
- 	return format_money($net);
+ 	return format_money($net, 'EUR');

InvalidArgumentException: Placeholder `$nett` does not stand in the template.

InvalidArgumentException: Placeholder `$amount` stands in the slot `variable` of `PhpSyntax\Nodes\ParameterNode`, which does not take `PhpSyntax\Nodes\Expression\PropertyFetchNode`.
```

New code is written as PHP, the language you already know, and the parts you are holding are put into
it by name:

```php
$builder->expression('$value ?? $default', value: $ternary->then, default: $ternary->else);
$builder->expression('$net * (1 + $rate)', net: $sum);
```

A **template** is a piece of code in which every variable named as a placeholder is replaced by the node
given; a variable named by nothing, `$rate`, stays the variable it is. Read the first line of the output:
the sum went under `*` and came out in parentheses. The builder puts each node in by
`replaceWithExpression()`, so a placeholder gets the parentheses its place needs and the template never
has to guess what will be put into it. Without them the line would compute `$cart['items'] +
$cart['shipping'] * (1 + $rate)`, and only the shipping would be taxed.

The second line is the other half of the deal. The sum still stands in the file, so the builder took a
**copy** and left the file alone; nothing changes until you put the result in with `replaceWith()`, which
keeps the comment after the statement where it was. A node standing nowhere the builder takes as it is.
What it drops is the whitespace on the edges of what it is given, because whitespace belongs to a place,
not to a node, and the new place has its own.

`statement()` takes a template too. A placeholder is a node, so a PHP value goes in through
**`value()`**, which writes it as the literal it is, quotes and escaping included. What the template
knows when you write it, it says as text; `value()` is for what the tool learns at run time, here the
currency of its settings.

The builder checks the placeholders before it builds anything, and the two exceptions at the end are
its checks: one the template does not have is a typo, and so is one its place could not hold, a
property fetch where only a variable may stand, as in the parameter of a closure.

## Building code from data: operators, casts, arrays and named arguments (building.php)

```php
$casts = ['intval' => 'int', 'floatval' => 'float', 'strval' => 'string', 'boolval' => 'bool'];
...
$call->replaceWithExpression($builder->cast($casts[$function], $call->arguments->items[0]->value));
```

```
left alone, a cast takes no base: intval($row['color'], 16)

- 	$id = intval($row['id']);
- 	$price = floatval($row['price']) * 1.21;
- 	$name = strval($row['name'] ?? '');   // a missing name is an empty one
+ 	$id = (int) $row['id'];
+ 	$price = (float) $row['price'] * 1.21;
+ 	$name = (string) ($row['name'] ?? '');   // a missing name is an empty one

($a + $b) * 2
-($a + $b)
['host' => 'localhost', 'port' => 3306, 'debug' => false, 'tags' => ['db', 'eu']]
json_encode($row, flags: JSON_THROW_ON_ERROR)
(new Logger('import'))->info('done', context: ['rows' => 3])
```

A template is right when you know the shape when you write the program. When the shape depends on data,
the rule here being a table that says which function becomes which cast, a template would have to be
glued together from strings, `'(' . $type . ') $x'`, and gluing code from strings is how a codemod writes
code that does not parse. So the builder has **methods for what data decide**: a name from a variable, an
operator as a string, a varying number of arguments. `cast()` takes the type as a value, `binary()` and
`unary()` the operator, `call()`, `methodCall()`, `staticMethodCall()` and `new()` the name and an array
of arguments.

What they give back is the same as what a template gives. The parentheses come where the operand needs
them: `(string) ($row['name'] ?? '')` keeps the meaning, `(float) $row['price'] * 1.21` needs none,
because a cast binds tighter than `*`. `($a + $b) * 2` and `-($a + $b)` got theirs from the side of the
operator they stand on, and `(new Logger('import'))->info()` because a call reaches into the `new`.

A value that is no node is written by **`value()`**, the PHP array included, as a short array on one line
with its keys where they are not the sequence from 0. And a string key among the arguments names the
argument, `'flags' => ...` becoming `flags: JSON_THROW_ON_ERROR`, which is how a codemod writes the named
arguments of a call whose list comes from data.

The function in the table is found by `NameResolver::findGlobalFunction()`, which tells which of the
names a call calls, inside a namespace too; [analyses/](../analyses) has the rest of name resolution.
Finding the function is not the whole question: `intval()` with a base reads a number in another
system, which no cast does, so the first line of the output is the call the rule leaves alone.

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

a trailing comma added:
- 	size: 10 // enough for the workers
+ 	size: 10, // enough for the workers
```

Adding an item to a list is where hand-written tools produce their ugliest output: a comma too many, an
item at column zero, a trailing comma in a one-line call. `SeparatedNodeList::insert()` models the list
it is inserting into. The separator is cloned from the separators already there, so a multi-line list
gets `,\n` and a one-line list gets `, `; one carrying a comment is no model, and neither is the trailing
comma of a one-line list, which stands right against the parenthesis. The new item takes the
indentation of its neighbor. The separator it brings is the one that goes with it, the one after it, so
the comment after `'mysql',` stays with the driver it is about. The trailing comma stays the trailing
comma.

`remove()` takes the separator that went with the item: the one after it, or for the last item the one
before it, which is how the trailing comma survives a removal at the end of
a list. `removeItem()` on the list does the same without tidying anything, so reach for `remove()`.

A `PlainNodeList` of statements has no separator to carry the line ending, so the inserted statement takes one
of its own, the way its neighbor ends its line; without that it would borrow the blank line that
followed the imports, and the diff would say two things instead of one.

The last step is a rule every coding standard has: a list written over several lines ends with a comma.
**`getTrailingSeparator()`** says whether there is one, and **`setTrailingSeparator()`** writes it where the
last item ended, which is before the comment, not after it at the end of the line, where it would be part
of the comment. `Token::fromText(',')` makes the token; the lexer decides its kind, so you do not look it up.
`null` removes the trailing comma, and its comment stays.

An item of a list is a fragment like any other: `$builder->fragment(ArrayItemNode::class, "'port' => 3306")`
gives it detached, with nothing of the code it was parsed in on its edges. Inserting a node that already
has a parent is a `LogicException` rather than a tree with two owners. There are two exceptions: a node
taken from inside what the write releases, which is how an operand is lifted up in place of what held it,
and a node of a fragment that stands in no file, where no index could come apart.

## Unwrapping a block, removing a statement and inserting a neighbor (statements.php)

```
a feature flag that is always on:
- 	if (NEW_TAX_RULES) { // on everywhere since March
- 		$order->applyTax(new TaxRate('EU'));
- 		$order->round(2);
- 	}
+ 	// on everywhere since March
+ 	$order->applyTax(new TaxRate('EU'));
+ 	$order->round(2);

a statement removed with its blank line:
- 	dump($order);
-

a statement inserted after its neighbor:
+ 	event(new OrderPlaced($order));
now before the return: event(new OrderPlaced($order));
```

Removing a feature flag that has been on for months is the kind of cleanup nobody does by hand, because
the hand-made diff is the whole block, re-indented. Here it is three steps. The `if` is replaced by its
own body, so the block stands among the statements, and **`BlockNode::unwrap()`** puts its statements in
its place and takes the braces away the way `remove()` takes a node: the comment on the opening brace is
not lost but goes before the first statement, on a line of its own. The statements keep their own
trivia, indentation included, so **`Indentation::shift()`** moves them a level up first.

The `dump()` stood between two blank lines. `remove()` takes its line, and **`mergeBlankLines: true`**
makes the two gaps one, the narrower of them; without it both would stay and the function would have
two blank lines in a row, which some formatter would have to clean up after you.

**`getNextSibling()`** and **`getPreviousSibling()`** are the neighbors of a node in the list it is an item
of, `null` at the ends, so a rule can ask what follows a statement without counting indexes. And
**`insertAfter()`** inserts next to a statement you hold, the way `insert()` does: the new statement takes
the indentation of its neighbor and ends its line the same way.

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
(new DateTime)   false        Fetch        the parent reaches inside them
($factory)       true         Call         nothing around them binds tighter
(FOO)            false        StaticAccess the parent reaches inside them
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

**`hasInnerComment()`** is the third question, and it is the one that gets forgotten, because `matches()`
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

Underneath sits the narrower question of how the parent reaches in at all, and `getDereferenceKind()`
answers it: `Fetch` for `->` and `[]`, `Call` for `(...)`, `StaticAccess` for `::`, and `null` where
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

A script that does several passes keeps its analyses: `NameResolver` reads the tree anew once
`$file->revision` says it has changed, and `Scope` holds nothing to go stale. The revision is also what
tells you a mutation happened; it counts writes rather than your calls, so compare it, never count on it.

## Try it yourself

1. In `text.php`, write `$name->text = 'Mailer'` and confirm the token kind changes with the name.
2. In `lists.php`, insert the array item at index 0 and watch the indentation follow the first item.
3. In `expressions.php`, wrap the lifted expression in a call instead of writing it bare:
   `$builder->call('strval', [$value])`, and see which of
   the three places still needs parentheses.
4. Put the two together: in `safety.php`, rewrite the lines marked "rewrite" into `$a += $b` with
   `$builder->combinedAssign($target, '+=', $sum->right)`, and check that the three left alone are still
   what they were.

## Further reading

- [trivia/](../trivia) - the whitespace helpers these rewrites build on
- [positions/](../positions) - the index that follows a mutation
- [internals](../../docs/internals.md) - the mutation protocol in full
