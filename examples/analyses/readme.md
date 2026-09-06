# Name resolution and scope: what a name refers to and where a node stands

Two analyses ship with the library, and between them they answer the questions a static analysis tool,
a linter or a refactoring script asks before it does anything: what class is this name, is this the
global `count()` or a namespaced one, is `$this` available here, what does this closure capture.

**You will learn:**

- how a name resolves against the namespace, the imports and PHP's global fallback
- how to write a fully qualified name back the way this file would write it
- what function, class and `$this` a node stands in
- what a real linter rule looks like when the tree does the bookkeeping

The two are built differently, and it matters after a mutation. `NameResolver` takes the `FileNode`,
reads the imports of a namespace the first time it is asked about one, and keeps what it learned, so
after you change the imports you build a new one; `FileNode::$revision` is how you notice. `Scope`
holds nothing at all and walks the tree as it is now, so it stays correct across mutations.

```shell
php examples/analyses/name-resolution.php
php examples/analyses/scope.php
php examples/analyses/unused-imports.php
```

## Resolving class, function and constant names against imports (name-resolution.php)

```
written          role       resolves to
Money            ClassLike  Shop\Money
TaxRate          ClassLike  Shop\Tax\Rate
format_money     Function   Shop\format_money
strtoupper       Function   strtoupper
CURRENCY         Constant   CURRENCY
Helper           ClassLike  Shop\Billing\Helper

format_money($sum) is a global strtoupper(): false
strtoupper(CURRENCY) is a global strtoupper(): true

Shop\Money             written here as Money
Shop\Tax\Rate          written here as TaxRate
Shop\Billing\Invoice   written here as Invoice
DateTimeImmutable      written here as \DateTimeImmutable

the alias Money is free here: false
the alias Invoice is free here: true
```

`NameResolver` implements PHP's own rules, and the interesting parts are the ones people get wrong by
hand. An alias wins over the namespace (`TaxRate`). A name with no import and no leading backslash is
namespace-relative for a class (`Helper` is `Shop\Billing\Helper`) but falls back to the global
namespace for a function or a constant, because that is what PHP does at run time (`strtoupper`,
`CURRENCY`). `isGlobalFunctionCall()` packages the question a rule about built-in functions actually
asks, and gets it right in a namespaced file, which is where naive tools fail.

**The way back matters just as much.** A tool that inserts code has to write a name the way this file
would write it. `getShortName()` gives the shortest form valid at that node: through an alias, through
an import of a prefix, relative to the namespace, or fully qualified with a leading backslash when
nothing else is safe. `isAliasFree()` answers "can I add an import under this alias here", which is the
other half of adding one.

## Enclosing function and class, and what $this means here (scope.php)

```
variable   in                     class      $this available
$rows      MethodNode             Report     true
$prefix    MethodNode             Report     true
$format    MethodNode             Report     true
$row       ClosureNode            Report     true
...
$row       ArrowFunctionNode      Report     false
...

the closure captures: $prefix
```

`Scope` walks upwards: the innermost function, method, closure, arrow function or property hook, and
the class around it. `hasThis()` is the one that repays reading the implementation: `$this` is
available inside the closure, because a non-static closure inside a method keeps it, and not inside the
arrow function, because that one is declared `static`. A tool that rewrites `$this` into something else,
or moves code between methods, has to know the difference, and getting it right involves a walk that
stops at the right kind of ancestor.

`getCapturedVariables()` lists what a closure takes with `use (...)`, which is what you check before
moving a statement into or out of one.

Both answers come back as interfaces rather than as long unions: `getFunction()` gives a
`FunctionLikeNode`, so `$params` and `$returnType` are there whether it is a function, a method, a
closure or a hook, and `getClass()` gives a `ClassLikeNode` with `$name` and `$members`, where an
anonymous class is the one whose `$name` is `null`. That is why the listing above prints the class name
without asking which of the five declarations it is.

## A linter rule in twenty lines: unused imports (unused-imports.php)

```
unused import on line 5: Shop\Tax\Rate
unused import on line 6: Shop\Receipt

- use Shop\Tax\Rate as TaxRate;
- use Shop\{Invoice, Receipt};
+ use Shop\{Invoice};
```

This is the whole thing: collect what the file refers to, resolved to fully qualified names, compare
with what it imports, and remove the imports nothing refers to. Two dozen lines including the printing
and the group-use arithmetic, and the removal is `remove()`, so a whole statement takes its line with
it while one item of a group leaves the rest of the group alone.

What the tree hands over here is most of the rule: which token is a name, which name is a class rather
than a function or a constant, what the imports of this file are, and whether a name resolves to the
imported class or to a different one of the same short name. Those questions are the reason the same
rule is long in a tool that works over a flat token array; it has to answer them first.

The two places a rule shipped to users would go further are both visible in the source. A class named
in a doc comment counts as a use, and the tree hands you the comment without reading it: what a doc
block means is the caller's business. And a group use left with a single item would be unwrapped by a
tidy rule, which is a formatting decision this library deliberately does not make for you.

## Try it yourself

1. Add `use Shop\Money as Cash;` to the sample and confirm the resolver reports the alias.
2. Make the closure in `scope.php` static and watch `$this` become unavailable in it.
3. Extend `unused-imports.php` to count a class named in a `@param` line as used.

## Further reading

- [tree/](../tree) - names as they are written, before resolution
- [mutation/](../mutation) - the removal this rule performs
- [internals](../../docs/internals.md) - what an analysis is and when it goes stale
