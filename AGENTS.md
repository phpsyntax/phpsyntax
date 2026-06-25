# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.


## Documentation

`docs/internals.md` is the source of truth for how PhpSyntax works: trivia rules, the round-trip invariant, the mutation API, the index, the generated files. Read it before any non-trivial change.

## Project overview

PhpSyntax is a **lossless concrete syntax tree** for PHP: every token of the source is in the tree, whitespace and comments are trivia attached to tokens, and printing the tree reproduces the input byte for byte. It is the layer a formatter, a refactoring tool or a code checker is built on.

One namespace, one PSR-4 root: `PhpSyntax` (`src/`) holds the lexer, the parser, the nodes, the printer, navigation, mutation and analyses. It has **no dependencies** and never imports Nette, php-parser or anything from `vendor/`; `composer.json` requires PHP and `ext-tokenizer` alone, everything else is `require-dev`. Everything is public API but for what says `@internal`.

## Essential commands

- `composer tester`: Nette Tester over `tests/`.
- `composer phpstan`: PHPStan level 8, no baseline; `ignoreErrors` only with a reason.
- `composer build`: regenerates `src/ParserData.php`, `src/TokenKind.php`, `src/LayoutData.php` and, in every node class, the `Slots` constant and the constructor from `grammar/` (`php.y` for the parser, `nodes.php` for the slots of the nodes); the rest of a node class is handwritten. Commit the output once the code style of `dresscode.neon` has run over it: what is committed is the formatted form, so a bare rebuild differs from it and that difference is no defect. The procedure for a new PHP version is in `docs/internals.md`.
- `composer reference`: regenerates `docs/reference/nodes.md` (node classes with their slots) from `grammar/nodes.php` and the classes. Commit the output.
- Round-trip over an external corpus: `PHPSYNTAX_CORPUS=/path/to/php/code composer tester`.

## Conventions

- Nette coding standard: tabs, `declare(strict_types=1)`, single quotes, types everywhere, two blank lines between methods.
- Modern PHP: `match` instead of `switch`, enums, `readonly`, promoted properties, named arguments, `never`.
- Naming:
  - methods are actions and start with a verb (`getFirstToken()`, `replaceChild()`); a bare noun is not a method name;
  - `get*` returns something that belongs to the object (may be `null`), `find*` searches and `null` means not found;
  - boolean queries `is*`/`has*`/`can*`, never `check*`, which is the name of a method that answers nothing and throws when the answer would be no; one whose truth means a nullable slot is filled says so with `@phpstan-assert-if-true`, or a typed caller cannot use it;
  - analyses carry bare names in `Analyses/`;
  - no `Abstract`, `Interface`, `I` or `Aware` prefixes/suffixes; an interface or base class sits next to the directory of its implementations;
  - enums of a namespace live in `enums.php`, exceptions in `exceptions.php`;
  - names of the API are written in full (`expression`, `condition`, `statements`, `arguments`, `parameters`, `variable`); the abbreviations left there are `paren` in `openParen`/`closeParen`, the delimiter having no one-word English name, `eol`, and `Op` in `BinaryOpNode` and its kin, which names the family across PHP tooling. A local variable may be abbreviated and often reads better for it (`$stmts`, `$eof`, `$args`). How a node class and its slots are named is in `docs/internals.md`, the tree growing once a year with a PHP version.
- Comments only where the code itself is not enough; never restate what the code shows; density follows the surrounding file. No phpDoc for what the types already say.
- Code, comments, identifiers and messages in English.

## Working rules

- Every unit of work (class, grammar production) ends with tests, PHPStan and a critical review of correctness, clarity, elegance and names. Fix findings immediately, not in a later commit.
- Round-trip `print(parse($code)) === $code` is an invariant: any change to the lexer, grammar, nodes or printer must pass the round-trip test over the committed corpus.
- Generated files, and the `Slots` constant and the constructor of a node class, are never edited by hand; change `grammar/` and rebuild. Everything else in a node class is handwritten and the generator leaves it alone.
- Grammar productions in `grammar/php.y` are not changed, only their actions.
- `Lexer`, `Parser`, `TokenIndex` and `Traverser` are parentless classes over generated data, and a node or a token is built by writing its properties: an acceleration extension would fill the same properties, hooks and all.
- One commit per unit, message lowercase, past tense, `subject: description` when it clarifies the area. Linear history.
- Committed files, commit messages and code comments never refer to documents outside the repository, nor to transient states of the work (milestones, phases, "until X exists"). Describe the current state; the history is in git.

## Traps

- `<?php` is not a token but `OpenTag` trivia carrying its whole text including the mandatory whitespace; it is always leading trivia of the following token.
- `?>` is a `CloseTag` token that keeps the newline PHP swallows after it; after a terminated statement it forms its own `EmptyStatementNode`.
- Trivia inside string interpolation (`"{$a /* c */}"`) carry `inInterpolation` and must never be reformatted.
- Whitespace that is part of a token stays in its text: inline HTML, heredoc delimiters, `( int )` casts, `T_ENCAPSED_AND_WHITESPACE`.
- `T_*` token ids differ between PHP builds; the lexer maps them by constant name, never by value.
- A grammar alternative with two or more symbols must have an action that uses every symbol, otherwise tokens drop out of the tree; `composer build` fails on such an alternative. A single symbol passes through by default.
- Semantic actions may put plain arrays of slot values on the value stack (alternative syntax tails, optional pairs like `: type`); they are spread into the node constructor and never leave the parser.
- Dump fixtures in `tests/PhpSyntax/Parser/dump/` are the oracle for the shape of the tree; after an intended change review the diff of the regenerated output, never paste it by hand.
- A slot of a node is written by assignment (`$node->condition = $expression`): its set hook moves the parents and tells the index. The text and the trivia of a token are written by `setText()`, `setLeadingTrivia()` and `setTrailingTrivia()`, because `?->` cannot stand on the left of an assignment. A property is written where the write takes one value and has one consequence, a method where it takes more or where two things change together (`StringNode::setValue($value, ?$quote)`). What has no hook the language guards instead: the items of a list are `protected(set)` and change only through the methods of the list, and `parent` is `private(set)`, written by `attachTo()` when the tree adopts or releases a child.
- `Node::getChildren()` is the only way to the children and `Node::find()` (a class or a predicate) to the descendants; a node is not iterable, and a child is never replaced by assigning to it: `replaceWith()` or the setter of the parent, which the `Traverser` notices and skips the replaced node.
- A node written into a new place carries the trivia of the place it came from, a clone and one moved out of a subtree without a file alike, so `setEdgeTrivia([], [])` clears them; only `Parser::parseFragment()` gives a node with empty edges. A write may take a node out of the child it releases and out of a subtree that has no file, never out of a live tree and never out of the node doing the write.
- `FileNode::$revision` is a version of the tree, not a count of mutations: a compound mutation such as `remove()` moves trivia in several steps and increments it several times. Compare it, never count on it.
- A mutation must keep the trivia canonical: the line ending that ends the line of a token belongs to that token's trailing trivia, never to the leading trivia of the next one. A misplaced one makes `getTrailingSpace()` blind to the line break; `ensureLeadingNewline()` and `setBlankLinesBefore()` place it correctly, so build on them.
- Whoever must not destroy a comment asks `Token::hasComment()`, `Token::hasCommentUpTo()` or removes one with `Token::removeTrivia()`; `Node::matches()` and `ExpressionNode::isRepeatableRead()` answer "does this expression repeat that one safely". Do not reimplement these locally.
