PhpSyntax
=========

**A lossless concrete syntax tree (CST) for PHP.** Every token, every space and every comment of the
source is in the tree, and printing the tree gives the file back byte for byte. Change one thing and the
diff contains one thing.

**Status: in development.** It works and a tool is already built on it, but until the first release
the API, names and behavior may still change.

PhpSyntax comes from David Grudl, the author of [Nette](https://nette.org), [Latte](https://latte.nette.org)
and [Tracy](https://tracy.nette.org). Latte already contains a parser of PHP expressions built on the
same grammar and the same build pipeline, so this is a second parser rather than a first attempt.

```php
$file = new PhpSyntax\Parser()->parse($code);

foreach ($file->find(PhpSyntax\Nodes\Member\MethodNode::class) as $method) {
	if ($method->name->text === 'notify') {
		$method->name->text = 'notifyCustomer';
	}
}

if ($file->revision > 0) {
	file_put_contents($path, (string) $file);
}
```

Nothing else in that file moves. Not the indentation of the untouched lines, not the blank lines, not
the comment somebody left three years ago, not the CRLF you did not notice the file had.

 <!---->

Why a concrete syntax tree (CST) instead of an AST: nikic/php-parser, PHP_CodeSniffer, PHP CS Fixer
==================================================================================================

An abstract syntax tree keeps what the code **means**. It has no place for the parentheses you wrote,
for the blank line before a method, or for the line a comment stood on relative to the code around it;
comments become an attribute of the nearest node. That is the right design for a compiler and for
static analysis, and it is why [nikic/php-parser](https://github.com/nikic/PHP-Parser) powers most PHP
tooling, PHPStan and Rector included.

It is the wrong shape for a tool that edits code a human will read afterwards. Printing an AST means
deciding the whitespace again, so php-parser grew a formatting-preserving printer, and its documentation
says what that costs: it needs the old tokens and the old tree next to the new one, and "works on a
best-effort basis and may sometimes reformat more code than necessary".

The other half of the ecosystem, PHP_CodeSniffer and PHP CS Fixer, goes the opposite way: a flat array
of tokens from `token_get_all()`, with the structure the tokenizer discarded rebuilt beside it, in an
index of scopes and parentheses the rules then read. Nothing wrong with the engineering; there simply
was no tree to work over.

PhpSyntax is the missing middle. A real parse tree, produced by the PHP grammar, in which nothing was
thrown away:

| | AST (php-parser) | flat tokens (PHPCS, PHP CS Fixer) | PhpSyntax |
|---|---|---|---|
| structure of the code | the shape of the data | an index beside the tokens | the shape of the data |
| whitespace and comments | attributes, partially | yes, as tokens | yes, as trivia on tokens |
| printing back unchanged | best effort | trivially | guaranteed, byte for byte |
| what a comment attaches to | the nearest node | its index in the array | the token it belongs to |
| error recovery | yes | yes | no |
| evaluating constant expressions | yes | no | no |

Use php-parser when you need semantics, constant evaluation or error recovery. Use PhpSyntax when you
need to touch the code and leave the rest of the file exactly as it is.

 <!---->

Installation
============

```shell
composer require phpsyntax/phpsyntax
```

Runs on PHP 8.4 to 8.6 and requires `ext-tokenizer`. **No package dependencies at all**, which is a design rule
rather than a boast: this library ends up in every project that runs the tool you build on it, so it
must not bring anyone else's version constraints with it. The syntax it reads is that of PHP 8.5, whichever
of those runtimes it runs on: on 8.4 through token emulation.

 <!---->

Parsing PHP source and printing it back: the round-trip invariant
=================================================================
▶ Runnable examples: [examples/parsing/](examples/parsing)

```php
use PhpSyntax\Parser;
use PhpSyntax\Printer;

$file = new Parser()->parse($code);
Printer::print($file) === $code;   // true, for any input PHP accepts
```

The round trip holds for BOM, hashbang, CRLF, `?>` with HTML after it, heredocs and the data after
`__halt_compiler()`. It is the invariant the test suite checks over a corpus of real and deliberately
hostile files, and the first thing to run over your own project before you build on the library.

Invalid code raises a `ParseException` saying where in the source it is, as a line, a column and a byte
offset. There is no error recovery and no partial tree: the grammar has none, and a formatter or a codemod
runs over code that compiles.

New nodes are made by parsing their text, so there is no builder API to learn:

```php
$parser->parseExpression('$price * (1 + $vat)');
$parser->parseStatement('if ($qty > 10) { $price *= 0.9; }');
$parser->parseType('int|string|null');
$parser->parseName('\Shop\Cart');
```

A codemod adds an item of a list far more often than a whole statement, and any node has a fragment of
its own; it comes back detached, ready to be put where it belongs:

```php
$parser->parseFragment(MemberNode::class, 'public function total(): float {}');
$parser->parseFragment(ParameterNode::class, "private string \$currency = 'EUR'");
$parser->parseFragment(ArrayItemNode::class, "'port' => 3306");
```

 <!---->

The syntax tree: nodes, named slots and tokens
==============================================
▶ Runnable examples: [examples/tree/](examples/tree) · 📖 [Nodes reference](docs/reference/nodes.md)

Children have names, not numbers. An `IfNode` has `ifKeyword`, `openParen`, `condition`, `closeParen`,
`body`, and the `colon`/`endKeyword` pair the alternative syntax fills instead:

```php
$if->condition;            // the condition
$if->body;                 // a statement, not necessarily a block; null in the alternative syntax
$if->elseifs;              // always a list, never null
$node->getChildren();      // in source order, the only way to the children
```

Every token of the source sits in a slot, so `(`, `;` and `}` are children like any other. Names are one
node with one token, and they answer questions about themselves: `$kind`, `$parts`, `$shortName`,
`$role`, which says whether the name stands for a class, a function or a constant, and `isDeclaration()`,
which tells an imported name from a used one.

Literals keep both halves of the truth. `$token->text` is `0x1F`, `$base` is 16, `$value` is 31; a string
knows its `$quote` and its resolved `$value`. A rule about notation is then three lines and cannot
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
$token->setBlankLinesBefore(1);
$token->ensureLeadingNewline();
$node->getDocComment()?->getCommentText();
$node->hasComment();                 // before you delete anything, ask
```

`hasComment()` and `hasCommentUpTo()` are small methods with a large job: they are how a rewriting tool
avoids destroying something a human wrote on purpose. Every such tool needs them, and every such tool
otherwise writes its own, slightly wrong.

 <!---->

Refactoring and codemods: rewriting PHP without reformatting it
===============================================================
▶ Runnable examples: [examples/mutation/](examples/mutation) · [examples/codemod/](examples/codemod)

A slot is written by assignment, and the write moves the parents and updates the token index:

```php
$if->condition = $parser->parseExpression('$order["items"] === []');
$call->replaceWith($replacement);
$statement->remove(CommentPolicy::MoveToNextToken);
$parameters->insert(2, $parameter);
```

A list knows how it is written: `insert()` clones the separator style already in use, gives the new item
the indentation of its neighbour, ends its line the way the neighbour ends it, and leaves a trailing comma
trailing. `remove()` takes the whole line when the node had a line to itself, takes the separator that
went with it, and moves the comment that stood above it, indentation and all, where the `CommentPolicy`
says. Taking an operand up in place of what held it needs no copy: what the write releases may be where
the new value comes from.

```php
$parenthesized->replaceWith($parenthesized->expression);
$assign->expression = $binary->right;
```

Before rewriting, the questions worth asking are methods rather than heuristics you write again:

```php
$a->matches($b);              // same code, whatever the whitespace between the tokens
$expr->isRepeatableRead();    // is reading it twice free of side effects
$node->hasComment();          // is there a comment the rewrite would destroy
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
$token->getLine();        // follows your mutations
$token->originalLine;     // where it was in the file on disk, forever
$token->getNext();        // token order, across node boundaries
$token->getLineWidth($style);
```

The token index is **kept up to date, not rebuilt**: a mutation reports what it changed, and the order
of the tokens and their lines are brought up to date lazily, as far as the next query reaches. The
shape every fixer has, read a line, change something, read the next line, therefore stays linear
instead of turning quadratic. That is the promise for whitespace and for the lines: columns and byte
offsets are recomputed for the whole file after a change, because an offset cannot be moved in pieces,
and a change that adds or removes nodes moves their tokens inside the order of the file.

 <!---->

Name resolution, imports and scope
==================================
▶ Runnable examples: [examples/analyses/](examples/analyses)

```php
$resolver = new NameResolver($file);
$resolver->resolveClass($name);                          // against namespace and imports
$resolver->isGlobalFunctionCall($call, 'count');         // right inside a namespace too
$resolver->getShortName($fullName, SymbolKind::ClassLike, $at); // and the way back

$scope = new Scope;
$scope->hasThis($node);                                  // is $this available here
```

`getShortName()` is the half most libraries leave out: to insert code you must write a name the way
*this* file would write it, through an alias, through an imported prefix, relative to the namespace, or
fully qualified when nothing else is safe.

 <!---->

Limits: no error recovery, no semantics, no formatter
=====================================================

Honest boundaries, so nobody discovers them at the wrong moment.

- **No semantics.** It does not know what a class inherits, what a constant evaluates to, or what type
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
- **A concrete tree costs more than an AST.** Every token is an object and carries its trivia, so a
  file is heavier here than in a parser that keeps only the meaning. That is the price of the round
  trip, and it is why tools built on this parse a file, finish with it, and move on.

 <!---->

Documentation
=============

- [examples/](examples) - runnable programs, one idea each; start with [parsing](examples/parsing) and
  then [mutation](examples/mutation)
- [docs/reference/nodes.md](docs/reference/nodes.md) - every node class with its slots, generated
- [docs/internals.md](docs/internals.md) - the trivia rules, the mutation protocol, the index

 <!---->

Credits
-------

- The PHP grammar (`grammar/php.y`) and the token emulators come from [nikic/php-parser](https://github.com/nikic/PHP-Parser), BSD-3-Clause.
- The parser build pipeline comes from [Latte](https://github.com/nette/latte).
