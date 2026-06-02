# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.

## Project overview

PhpSyntax is a **lossless concrete syntax tree** for PHP: every token of the source is in the tree, whitespace and comments are trivia attached to tokens, and printing the tree reproduces the input byte for byte. It is the layer a formatter, a refactoring tool or a code checker is built on.

One namespace, one PSR-4 root: `PhpSyntax` (`src/`) holds the lexer, the parser, the nodes, the printer, navigation, mutation and analyses. It has **no dependencies** and never imports Nette, php-parser or anything from `vendor/`; `composer.json` requires PHP and `ext-tokenizer` alone, everything else is `require-dev`. Everything is public API but for what says `@internal`.

## Essential commands

- `composer tester`: Nette Tester over `tests/`.
- `composer phpstan`: PHPStan level 8, no baseline; `ignoreErrors` only with a reason.
- `composer verify-examples`: runs the scripts of `examples/` and compares their output with what the readme of each chapter quotes, and checks that the PHP blocks of `readme.md` parse.
- `composer compile-grammar`: regenerates `src/ParserData.php` and `src/TokenData.php` from `grammar/php.y`. Commit the output once the code style of `dresscode.neon` has run over it: what is committed is the formatted form, so a bare rebuild differs from it and that difference is no defect.

## Conventions

- Nette coding standard: tabs, `declare(strict_types=1)`, single quotes, types everywhere, two blank lines between methods.
- Modern PHP: `match` instead of `switch`, enums, `readonly`, promoted properties, named arguments, `never`.
- The kind of a token or a trivia is asked by `is()` (`$token->is(Token::Variable)`, `$token->is(';')`, `$trivia->is(Trivia::DocComment)`). `->id` stays where the kind is a value, compared with another one or a key (`$a->id !== $b->id`, `isset($map[$token->id])`), and on a hot path (the lexer, the parser, the index, the printer, `Token`, `Trivia`), where the call costs twice the comparison. A set of several kinds asked over every token is a constant map and `isset()`, an array literal in `is([...])` being built anew on every call.
- Naming:
  - methods are actions and start with a verb (`getFirstToken()`, `replaceChild()`); a bare noun is not a method name;
  - `get*` returns something that belongs to the object (may be `null`), `find*` searches and `null` means not found;
  - boolean queries `is*`/`has*`/`can*`, never `check*`, which is the name of a method that answers nothing and throws when the answer would be no; one whose truth means a nullable slot is filled says so with `@phpstan-assert-if-true`, or a typed caller cannot use it; a boolean property is named by the state itself (`$active`), never `$isActive`;
  - analyses carry bare names in `Analyses/`;
  - no `Abstract`, `Interface`, `I` prefixes/suffixes; an interface or base class sits next to the directory of its implementations;
  - enums of a namespace live in `enums.php`, exceptions in `exceptions.php`;
  - names of the API are written in full (`expression`, `condition`, `statements`, `arguments`, `parameters`, `variable`); the abbreviations left there are `paren` in `openParen`/`closeParen`, the delimiter having no one-word English name, and `Op` in `BinaryOpNode` and its kin, which names the family across PHP tooling. A local variable may be abbreviated and often reads better for it (`$stmts`, `$eof`, `$args`).
- Comments only where the code itself is not enough; never restate what the code shows; density follows the surrounding file. No phpDoc for what the types already say; the `@method Token getFirstToken()` and `getLastToken()` of a class that always has a token say what the inherited nullable type cannot.
- Code in a doc comment is written in backticks: a call, a variable or a property, an operator, a keyword, a literal, the case of an enum (`setEdgeTrivia()`, `$this`, `?->`, `static`, `''`, `Stop`). Doc comments are read as Markdown, in an IDE and in `docs/reference/nodes.md`, and what stands bare there is typeset as text: `->` becomes an arrow, `...` an ellipsis. Backticks mark the code itself, so what only resembles code stays bare, whatever the IDE makes of it: a keyword used as a word (a static method, a readonly class, but a `match` expression), `null`, `true` and `false` in "null when …", and the `=>` describing a map (path => its size).
- Code, comments, identifiers and messages in English.

## Working rules

- Every unit of work (class, grammar production) ends with tests, PHPStan and a critical review of correctness, clarity, elegance and names. Fix findings immediately, not in a later commit.
- One commit per unit, message lowercase, past tense, `subject: description` when it clarifies the area. Linear history.
- Committed files, commit messages and code comments never refer to documents outside the repository, nor to transient states of the work (milestones, phases, "until X exists"). Describe the current state; the history is in git.
