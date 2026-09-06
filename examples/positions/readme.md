# Line and column positions in a PHP CST: a token index that survives an edit

A tool reads a position, changes something, and reads the next position. That loop is the shape of
every code fixer, and it is where a naive index turns a linear job into a quadratic one.

**You will learn:**

- the difference between the current line and the line the token had on disk
- how the index follows a mutation instead of being rebuilt, and why that matters for a fixer
- how to walk the file token by token, across node boundaries
- how to measure a line the way a line-length rule has to

```shell
php examples/positions/lines.php
php examples/positions/navigation.php
```

## Lines, columns and offsets that follow an edit (lines.php)

```
return is on line 6, column 3, offset 56
after the edit: line 8, originally 6
the node agrees: 8

Cart::total() returns on line 8 of the rewritten file
which was line 6 of the file on disk
```

Two blank lines are inserted above the class, and the `return` statement, which nobody touched, reports
line 8. Not because the tree was reparsed and not because the index was thrown away: a mutation reports
what it changed, and **lines and token order are brought up to date lazily**, only as far as the next
query reaches. An edit followed by a query near it therefore costs the distance between them, not the
size of the file.

Two positions are not lazy, and it is worth knowing which. `getOffset()` and `getColumn()` are
recomputed for the whole file after any change, because a byte offset cannot be moved in pieces. A rule
that measures columns in a loop should read them before it starts editing, or work in the other order.

Meanwhile `$token->originalLine` and `$originalOffset` never move: they are where the token was in the
file as it was read. That is the pair a report wants, the current line to show the user the code they
are looking at now, the original line to point at the file on disk.

`getColumn()` counts UTF-8 characters, and `getVisualColumn(Style)` expands tabs, which is the one a
column-limit rule means.

## Walking a PHP token stream: getNext(), getPrevious() and line length (navigation.php)

```
the token before "[" is "="
the token after "[" is "'driver'"

commas by is(','): 3
commas by comparing the text: 4

line 4: width 61, strlen 58, indented with "\t"
line 5: width  5, strlen  2, indented with "\t"
line 6: width 56, strlen 50, indented with "\t\t"
...
```

`getNext()` and `getPrevious()` walk the file token by token, across node boundaries, which is how you
answer questions the tree does not group for you: what stands before this bracket, is anything between
these two tokens, where does this line begin. The tree gives you structure, the token order gives you
text order, and a real tool uses both.

**`is()` never matches inside a string, and the run shows what that saves you.** The file contains
`"{$driver},{$host}"`, where the comma between the two interpolations is a token of its own carrying
string content. Comparing token texts finds four commas; `is(',')` finds the three that are
punctuation. Given a text it matches operators and punctuation only, given a token kind it matches
kinds, and that is the difference between a rule that works on real files and one that misfires inside
strings, inline HTML and heredoc bodies.

**`getLineWidth()` measures a line the way an editor does**, counting tabs in the indentation as the
style's tab width and ignoring trailing whitespace, which is why the widths above differ from
`strlen()` by exactly the tabs. A "line too long" rule is then a `startsLine()` filter and a
comparison, and it agrees with what the developer sees. (A tab further along the line, in a hand-made
alignment, counts as one character.)

## Try it yourself

1. In `lines.php`, remove the statement instead of adding blank lines and check that the lines below
   move up.
2. Print the ten widest lines of one of your own source files.
3. Walk from the first token to the last and print only tokens where `startsLine()` is true; you have
   just listed the first token of every line.

## Further reading

- [internals](../../docs/internals.md) - how the index is kept up to date
- [mutation/](../mutation) - edits that the index follows
