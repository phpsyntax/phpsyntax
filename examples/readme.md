# PhpSyntax by example

Runnable programs, one idea each. Every one of them parses a small piece of PHP, does something to it,
and prints what happened, so you can read the output next to the code that produced it.

```shell
composer install
php examples/parsing/round-trip.php
```

Nothing here needs configuration, a network or a temp directory. The samples are inside the scripts, so
an example is one file, plus the four helpers in `bootstrap.php`, that you can copy into your own project
and start changing.

## Where to start

Read them in this order; each directory has its own readme.

| | |
|---|---|
| [parsing/](parsing) | parse PHP into a tree, print it back byte for byte, build nodes from fragments |
| [tree/](tree) | nodes, named slots, tokens, trivia, names and literals |
| [trivia/](trivia) | comments and whitespace: reading them, keeping them, changing them |
| [positions/](positions) | lines, columns and token navigation that survive an edit |
| [mutation/](mutation) | rewriting code so that the diff is only what you changed |
| [analyses/](analyses) | resolving names against imports, and the scope a node stands in |
| [codemod/](codemod) | everything together: migrating a deprecated API, and steering a walk |
| [edge-cases/](edge-cases) | BOM, hashbang, CRLF, close tags, heredocs and `__halt_compiler()` |

If you have ten minutes, read [parsing/](parsing) and then [mutation/](mutation): the first says what
the library promises, the second shows what the promise buys you.

When a script makes you wonder what the tree of some other code looks like, the command line shows it:
`vendor/bin/phpsyntax dump -e '<?php ...'`, or `dump file.php` for a whole file.

## Checking the examples

```shell
composer verify-examples
```

Every readme quotes real output. The script runs each example and checks that what its readme shows is
still what it prints, so the text cannot drift away from the code. It runs in CI too.

## A note on the samples

The sample code inside the scripts is deliberately imperfect: inconsistent spacing, comments in awkward
places, a heredoc, a close tag. That is the point. A tool that only survives tidy input is not a tool
you can run over a code base you did not write.
