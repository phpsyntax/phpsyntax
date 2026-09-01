PhpSyntax
=========

[![Tests](https://github.com/phpsyntax/phpsyntax/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/phpsyntax/phpsyntax/actions)
[![Coverage Status](https://coveralls.io/repos/github/phpsyntax/phpsyntax/badge.svg?branch=master)](https://coveralls.io/github/phpsyntax/phpsyntax?branch=master)
[![Latest Stable Version](https://poser.pugx.org/phpsyntax/phpsyntax/v/stable)](https://github.com/phpsyntax/phpsyntax/releases)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/phpsyntax/phpsyntax/blob/master/license.md)

 <!---->

<h3>

✅ A PHP parser that keeps [every space and comment](#comments-and-whitespace-as-trivia-on-tokens)<br>
✅ [Rewrite code](#refactoring-and-codemods-rewriting-php-without-reformatting-it) without reformatting the rest of the file<br>
✅ Built for [codemods, fixers and upgrade tools](#why-a-concrete-syntax-tree-cst-instead-of-an-ast-nikicphp-parser-php_codesniffer-php-cs-fixer)<br>
✅ [No package dependencies](#installation) at all

</h3>

 <!---->

**A lossless concrete syntax tree (CST) for PHP.** Every token, every space and every comment of the
source is in the tree, and printing the tree gives the file back byte for byte:

```php
$file = new PhpSyntax\Parser()->parse($code);

PhpSyntax\Printer::print($file) === $code;   // true, for any input PHP accepts
```

An abstract syntax tree, which most PHP tooling is built on, cannot promise that line. This library is
built around it: change one thing, and the diff contains one thing.

```php
foreach ($file->find(PhpSyntax\Nodes\Member\MethodNode::class) as $method) {
	if ($method->name->text === 'notify') {
		$method->name->text = 'notifyCustomer';
	}
}

if ($file->revision > 0) {      // did anything actually change?
	file_put_contents($path, (string) $file);
}
```

Nothing else in that file moves. Not the indentation of the untouched lines, not the blank lines, not
the comment somebody left three years ago, not the CRLF you did not notice the file had.

That is what you build on it: coding-standard fixers, codemods, deprecation upgrades, code generators,
linters that repair what they find, any tool that writes back into a file a human will open next.
[DressCode](https://github.com/dg/dresscode), a coding-style checker and upgrade tool, is built on it.

PhpSyntax comes from David Grudl, the author of [Nette](https://nette.org), [Latte](https://latte.nette.org)
and [Tracy](https://tracy.nette.org). Latte already contains a parser of PHP expressions built on the
same grammar and the same build pipeline, so this is a second parser rather than a first attempt.

 <!---->

Installation
============

```shell
composer require phpsyntax/phpsyntax
```

Runs on PHP 8.4 to 8.6 and requires `ext-tokenizer`. **No package dependencies at all**, which is a design rule
rather than a boast: this library ends up in every project that runs the tool you build on it, so it
must not bring anyone else's version constraints with it. The syntax it reads is that of PHP 8.5 with the
partial function application of PHP 8.6 (`str_replace('a', ?, $text)`), whichever of those runtimes it
runs on: on 8.4 through token emulation.

 <!---->

Why a concrete syntax tree (CST) instead of an AST: nikic/PHP-Parser, PHP_CodeSniffer, PHP CS Fixer
===================================================================================================

An abstract syntax tree keeps what the code **means**. It has no place for the parentheses you wrote,
for the blank line before a method, or for the line a comment stood on relative to the code around it;
comments become an attribute of the nearest node. That is the right design for a compiler and for
static analysis, and it is why [nikic/PHP-Parser](https://github.com/nikic/PHP-Parser) powers most PHP
tooling, PHPStan and Rector included.

It is the wrong shape for a tool that edits code a human will read afterwards. Printing an AST means
deciding the whitespace again, so PHP-Parser grew a formatting-preserving printer that needs the old
tokens and the old tree next to the new one, and its documentation says what that costs: it "works on a
best-effort basis and may sometimes reformat more code than necessary". Here the round trip is not a
best effort, it is the invariant the test suite checks over a corpus of real and deliberately hostile
files.

The other half of the ecosystem, PHP_CodeSniffer and PHP CS Fixer, goes the opposite way: a flat array
of tokens from `token_get_all()`, with the structure the tokenizer discarded rebuilt beside it, in an
index of scopes and parentheses the rules then read. Nothing wrong with the engineering; there simply
was no tree to work over.

PhpSyntax is the missing middle. A real parse tree, produced by the PHP grammar, in which nothing was
thrown away:

| | AST (PHP-Parser) | flat tokens (PHPCS, PHP CS Fixer) | PhpSyntax |
|---|---|---|---|
| structure of the code | the shape of the data | an index beside the tokens | the shape of the data |
| whitespace and comments | attributes, partially | yes, as tokens | yes, as trivia on tokens |
| printing back unchanged | best effort | trivially | guaranteed, byte for byte |
| what a comment attaches to | the nearest node | its index in the array | the token it belongs to |
| error recovery | yes | yes | no |
| evaluating constant expressions | yes | no | literals and arrays of them (`toValue()`) |

Use PHP-Parser when you need semantics, evaluation beyond literals or error recovery. Use PhpSyntax when you
need to touch the code and leave the rest of the file exactly as it is.

 <!---->

Parsing PHP source and creating new nodes
=========================================
▶ Runnable examples: [examples/parsing/](examples/parsing) · [examples/mutation/builder.php](examples/mutation/builder.php) · [examples/mutation/building.php](examples/mutation/building.php)

The round trip from the top of this page holds for BOM, hashbang, CRLF, `?>` with HTML after it, heredocs
and the data after `__halt_compiler()`, and it is the first thing to run over your own project before you
build on the library:

```shell
vendor/bin/phpsyntax check src
```

Code the grammar refuses raises a `ParseException` saying where in the source it is, as a line, a column
and a byte offset. There is no error recovery and no partial tree: the grammar has none, and a formatter or a
codemod runs over code that compiles. A tree is no promise that it does, though: what only the compiler
refuses, `break` outside a loop for one, parses.

New nodes are made by parsing their text; a whole file is the parser's, anything less the builder's:

```php
$builder = new Builder;
$builder->expression('$price * (1 + $vat)');
$builder->statement('if ($qty > 10) { $price *= 0.9; }');
$builder->type('int|string|null');
$builder->name('\Shop\Cart');
```

A codemod adds an item of a list far more often than a whole statement, and every kind of list item has
a fragment of its own; it comes back detached, ready to be put where it belongs:

```php
$builder->fragment(MemberNode::class, 'public function total(): float {}');
$builder->fragment(ParameterNode::class, "private string \$currency = 'EUR'");
$builder->fragment(ArrayItemNode::class, "'port' => 3306");
```

What a rewrite puts together out of nodes it is already holding cannot be written as text, so the
`Builder` does it. A template keeps the shape and names its placeholders; a method of its own serves where
data decide, a name from a variable, an operator as a string, a varying number of arguments. Either way the
builder makes the operator and delimiter tokens and the parentheses without which the result would not read
back the same way, and a node that still stands in the file goes in as a copy without its edge whitespace:

```php
$builder->expression('$total * (1 + $vat)', total: $sum);   // ($a + $b) * (1 + $vat)
$builder->new('DateTime');                                  // new DateTime
$builder->methodCall($builder->new('Foo'), 'bar', [1, 'flags' => 2]);  // (new Foo)->bar(1, flags: 2)
$builder->combinedAssign($total, '+=', $vat);               // $total += $vat
$builder->value(['port' => 3306]);                          // ['port' => 3306]
```

 <!---->

The syntax tree: nodes, named slots and tokens
==============================================
▶ Runnable examples: [examples/tree/](examples/tree) · 📖 [Nodes reference](docs/reference/nodes.md)

Children have names, not numbers. An `IfNode` has `ifKeyword`, `openParen`, `condition`, `closeParen`,
`body`, and the `colon`, `statements` and `endKeyword` the alternative syntax fills instead:

```php
$if->condition;            // the condition
$if->body;                 // a statement, not necessarily a block; null in the alternative syntax
$if->elseifs;              // always a list, never null
$node->getChildren();      // in source order, the only way to the children
```

Every token of the source sits in a slot, so `(`, `;` and `}` are children like any other. Names are one
node with one token, and they answer questions about themselves: `$form`, `$parts`, `$shortName`,
`$symbolKind`, which says whether the name stands for a class, a function or a constant, and `isDeclaration()`,
which tells an imported name from a used one.

Literals keep both halves of the truth. `$token->text` is `0x1F`, `$base` is 16, `toValue()` is 31; a string
knows its `$quote` and its resolved `toValue()`. A rule about notation is then three lines and cannot
rewrite what it did not mean to.

 <!---->

Comments and whitespace as trivia on tokens
===========================================
▶ Runnable examples: [examples/trivia/](examples/trivia)

Whitespace and comments are **trivia attached to tokens**, under one rule: what follows a token up to
and including the end of its line is that token's trailing trivia; everything else is the leading trivia
of the next token.

```php
$token->getTrailingSpace();          // the space up to the next token; null when a line ending
                                     // or a comment is in the way
$token->setTrailingSpace(' ');
$token->ensureStartsLine("\n");      // onto its own line, and then
$token->setBlankLinesBefore(1, "\n"); // a blank line above it
$node->getDocComment()?->getCommentText();
$node->hasInnerComment();            // before you delete anything, ask
```

`hasInnerComment()` and `hasCommentUpTo()` are small methods with a large job: they are how a rewriting tool
avoids destroying something a human wrote on purpose. Every such tool needs them, and every such tool
otherwise writes its own, slightly wrong.

 <!---->

Refactoring and codemods: rewriting PHP without reformatting it
===============================================================
▶ Runnable examples: [examples/mutation/](examples/mutation) · [examples/codemod/](examples/codemod)

A slot is written by assignment, and the write moves the parents and updates the token index:

```php
$if->condition = $builder->expression('$order["items"] === []');
$call->replaceWith($replacement);
$statement->remove(mergeBlankLines: true);
$parameters->insert(2, $parameter);
```

A list knows how it is written: `insert()` clones the separator style already in use, gives the new item
the indentation of its neighbor, ends its line the way the neighbor ends it, and leaves a trailing comma
trailing. `remove()` takes the whole line when the node had a line to itself, takes the separator that
went with it, and moves the comment that stood right above it, indentation and all, where the
`CommentPolicy` says, the header of a section set apart by a blank line staying where it is. With `mergeBlankLines` the blank lines above and below the node become one gap, the narrower of
the two, so a statement removed from between two blank lines does not leave two in a row. Taking an
operand up in place of what held it needs no copy: what the write releases may be where the new value
comes from.

```php
$parenthesized->replaceWith($parenthesized->expression);
$assign->expression = $binary->right;
```

An expression written where operators stand around it is the one place a verbatim write quietly gets
wrong. `replaceWithExpression()` writes it in parentheses and takes them away again where `isRedundant()`
calls them needless, so lifting the argument out of `ucfirst($a ?? $b) . 'x'` gives `($a ?? $b) . 'x'`
and not an expression that means something else. `replaceWith()` guards the other seam by itself: where
the new node ends up against a token the lexer would read it together with, `.` against `119`, a space
goes in between. The lexer's `canAdjoin()` is that question, for when you are the one taking whitespace
away.

Before rewriting, the questions worth asking are methods rather than heuristics you write again:

```php
$a->matches($b);              // same code, whatever the whitespace between the tokens
$expr->isRepeatableRead();    // is reading it twice free of side effects, as far as the syntax tells
$node->hasInnerComment();     // is there a comment the rewrite would destroy
$parenthesized->isRedundant(); // may these parentheses go
$call->arguments->findArgument('object', 0); // the argument a parameter gets, named or not
```

The first three turn `$a = $a + $b` into `$a += $b` safely, and leave alone both
`$counts[$i++] = $counts[$i++] + 1` and an assignment with a comment in the middle.

 <!---->

Lines, columns and token positions that survive an edit
=======================================================
▶ Runnable examples: [examples/positions/](examples/positions)

```php
$token->getCurrentLine();  // follows your mutations
$token->line;             // where it was in the file on disk, forever
$token->getNext();        // token order, across node boundaries
Indentation::measureLineWidth($token, $style);
$file->findNode($start, $end);   // a position from an editor or another tool, back to a node
```

The token index is **kept up to date, not rebuilt**: a mutation reports what it changed, and the order
of the tokens and their lines are brought up to date lazily, as far as the next query reaches. The
shape every fixer has, read a line, change something, read the next line, therefore stays linear
instead of turning quadratic. Columns and byte offsets follow the same way, as far as the queries reach.
What costs the whole file is `findNode()`, which needs the positions of every token, and a change that
adds or removes nodes, which moves the tokens after it inside the order of the file.

 <!---->

Name resolution, imports and scope
==================================
▶ Runnable examples: [examples/analyses/](examples/analyses)

```php
$resolver = new NameResolver($file);
$resolver->resolve($name);                               // a class, a function or a constant, against namespace and imports
$resolver->findGlobalFunction($call, ['count', 'strlen']); // which one is called, inside a namespace too, taking PHP's fallback
$resolver->getUnqualifiedResolution($call->name);        // and whether the fallback is certain
$resolver->shortenName($fullName, SymbolKind::ClassLike, $at); // and the way back
$resolver->findDeclaration('Shop\Billing\Invoice', SymbolKind::ClassLike); // which declaration in this file is it

$use->addImport('Shop\Money', alias: 'Cash');            // written the way this statement writes items
$item->remove();                                         // the whole statement where it is its only item

$node->findAncestor(FunctionLikeNode::class);            // the function, method, closure or hook around it
$scope = new Scope;
$scope->hasThis($node);                                  // is $this available here
```

`shortenName()` is the half most libraries leave out: to insert code you must write a name the way
*this* file would write it, through an alias, through an imported prefix, relative to the namespace, or
fully qualified when nothing else is safe.

Imports are the other half. An item of a `use` statement means what the statement says, so in a group
its name is written under the prefix and carries none of its own. `addImport()` takes the fully
qualified name and writes it the way that statement writes the rest, and refuses a name that does not
belong in the group; `UseItemNode::$fullName` reads it back the same way, prefix included.

 <!---->

The command line tool
=====================

`vendor/bin/phpsyntax` reads a file, a directory, standard input or the code of `--eval`, and answers the
questions a tool built on the library raises while it is being written (the examples leave the path out):

```shell
phpsyntax check src                     # parses each file and prints it back; a difference is a failure
phpsyntax dump --as=expression -e '$a ?? $b'
phpsyntax dump --at=42:17 src/Cart.php  # the innermost node there, and what it sits in
phpsyntax tokens src/Cart.php           # what the lexer made of the code, emulated kinds included
phpsyntax resolve src/Cart.php          # what each name refers to, imports and namespace applied
phpsyntax find MethodCallNode src       # where a node class occurs, and the code it stands for
```

The exit code is the verdict, so `check` belongs in a pipeline: 0 all well, 1 a failure or nothing found,
2 a wrong invocation. `phpsyntax --help` lists the options of each command.

 <!---->

Performance
===========

At its core PhpSyntax is fast: its LALR automaton gets through a file faster than the one of
[nikic/PHP-Parser](https://github.com/nikic/PHP-Parser), and building a node costs about the same. The
difference is in how much the tree holds. Every space and comment is gathered onto its token, and there
are about a third more nodes, because lists, parentheses and modifiers are nodes of their own. Parsing
typical code therefore takes about 1.5× as long as with PHP-Parser, and about 1.2× as long as with
PHP-Parser connecting the parents, which gives the same kind of tree, one in which every node knows its
parent.

After the parse, the richer tree pays that back:

- A query walking up the tree takes about half the time, because PHP-Parser first has to walk the whole
  tree to connect the parents.
- An edit printed back costs about the same as with the format-preserving printer of PHP-Parser, which
  keeps the old tree and tokens next to the new ones; PhpSyntax just joins the tokens. And where
  PHP-Parser dropped comments while removing the first statement of a file, 420 of them over a sample of
  WordPress, PhpSyntax kept every one.
- The tree takes less memory than PHP-Parser with the tokens its lossless printing needs.
- A cold start, the autoload and the round trip of a small file, takes as long as with PHP-Parser.

Measured in September 2026 on PHP 8.5 with opcache off, against PHP-Parser 5.9, over samples of Symfony,
Laravel, WordPress, Nette and others.

 <!---->

Limits: no error recovery, no semantics, no formatter
=====================================================

Honest boundaries, so nobody discovers them at the wrong moment.

- **No semantics.** PhpSyntax does not know what a class inherits, what a constant evaluates to, or what type
  an expression has. It is a syntax layer. What it reads is what the file itself says: a name is resolved
  against the imports and the namespace, and a function or a constant the file does not declare is taken
  as global, the way PHP falls back to it, which is a guess about other files and not knowledge of them.
- **No error recovery.** Source the grammar refuses throws a `ParseException`, and there is no partial
  tree. What PHP refuses only when it compiles, such as a write through `?->`, parses: the tree checks the
  syntax, not everything the compiler checks.
- **No formatter.** It never reformats what you did not touch, and it has no idea what tidy means. What it
  writes for you follows the code around it: an inserted item takes the indentation of its neighbor, and
  `Indentation::infer()` tells a tool the indentation a line conventionally has. What the code should look
  like is for the tool built on it to decide.
- **The nodes are the library's own.** A class of yours extending a node or a list, or implementing an
  interface of the nodes, is not supported: such an interface may gain a member, and what says `@internal`
  may change in any version.
- **Parsing takes longer than with PHP-Parser**, about 1.5×, because the tree holds more;
  [Performance](#performance) has the numbers and what the tree gives back for them.

 <!---->

Documentation
=============

- [phpsyntax.deegee.dev](https://phpsyntax.deegee.dev) - the user manual
- [examples/](examples) - runnable programs, one idea each; start with [parsing](examples/parsing) and
  then [mutation](examples/mutation)
- [docs/reference/nodes.md](docs/reference/nodes.md) - every node class with its slots, properties and methods, generated
- [docs/migrating-from-php-parser.md](docs/migrating-from-php-parser.md) - moving a tool off nikic/PHP-Parser: what maps onto what
- [docs/internals.md](docs/internals.md) - the trivia rules, the mutation protocol, the index

 <!---->

Credits
-------

- The PHP grammar (`grammar/php.y`) and the token emulators come from [nikic/PHP-Parser](https://github.com/nikic/PHP-Parser)
  by Nikita Popov, BSD-3-Clause.
- The parser build pipeline comes from [Latte](https://github.com/nette/latte).
