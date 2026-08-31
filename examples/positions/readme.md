# Line and column positions in a PHP CST: a token index that survives an edit

A tool reads a position, changes something, and reads the next position. That loop is the shape of
every code fixer, and it is where a naive index turns a linear job into a quadratic one.

**You will learn:**

- the difference between the current line and the line the token had on disk
- how the index follows a mutation instead of being rebuilt, and why that matters for a fixer
- how to walk the file token by token, across node boundaries
- how to measure a line the way a line-length rule has to
- how to turn the byte offsets another tool reports into a node, and a node back into offsets

```shell
php examples/positions/lines.php
php examples/positions/navigation.php
php examples/positions/offsets.php
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

Columns and byte offsets follow the same way: `getCurrentColumn()` and `getCurrentOffset()` are brought
up to date as far as the query reaches, so a rule that reads a position, edits and reads the next one pays
for the distance. Two things cost the whole file. `findNode()` needs the positions of every token, and a
change of structure, a node inserted or removed, moves the tokens after it.

Meanwhile `$token->line` and `$token->pos` never move: they are where the token was in the
file as it was read. That is the pair a report wants, the current line to show the user the code they
are looking at now, the original line to point at the file on disk.

`getCurrentColumn()` counts UTF-8 characters, and `getVisualColumn(Style)` expands tabs, which is the one a
column-limit rule means.

## Walking a PHP token stream: getNext(), getPrevious() and line length (navigation.php)

```
the token before "[" is "="
the token after "[" is "'driver'"

commas by kind, is(ord(',')): 3
commas by text, is(','): 4

line 4: width 61, strlen 58, indented with "\t"
line 5: width  5, strlen  2, indented with "\t"
line 6: width 56, strlen 50, indented with "\t\t"
...
```

`getNext()` and `getPrevious()` walk the file token by token, across node boundaries, which is how you
answer questions the tree does not group for you: what stands before this bracket, is anything between
these two tokens, where does this line begin. The tree gives you structure, the token order gives you
text order, and a real tool uses both.

**Ask `is()` by the kind when a string may stand in the way.** The file contains `"{$driver},{$host}"`,
where the comma between the two interpolations is a token of its own carrying string content. `is(',')`
compares the text, so it finds four commas; `is(ord(','))` compares the kind, and the comma inside the
string is of the kind of string content, so it finds the three that are punctuation. A loop over every
token of the file asks by the kind, or it misfires inside strings, inline HTML and heredoc bodies; asked
of a slot, such as the operator of a binary operation, the text reads better and means the same.

**`Indentation::measureLineWidth()` measures a line the way an editor does**: every tab reaches to the
next tab stop of the style, wherever on the line it stands, which is why the widths above differ from
`strlen()` by the tabs that indent the lines. A "line too long" rule is then a `startsLine()` filter and a
comparison, and it agrees with what the developer sees. `getTokens()` of the file is the whole token
stream in order, for the loops that visit every token.

## From a byte offset to a node, for editors and other tools (offsets.php)

```
reported: bytes 66 to 82
found:    $db->query($sql)
as Node:  MethodCallNode
shifted:  NULL
$db stands at bytes 66 to 69
the method renamed to select(), the call ends at byte 83
a token of a fragment is in this file: false
```

Other tools speak in positions: an editor sends the selection, a static analyser reports a problem at
bytes 66 to 82, another parser gives the offsets of its own node. **`findNode()`** of the file brings
such a position to the tree: the outermost node of the class you ask for whose text stands
exactly at those offsets, the end exclusive. Without a class it is the outermost node of all, which is
the call and not the statement around it, because the statement's text is longer. A range that is off by
one finds nothing, and that is the honest answer: the two tools do not agree on what stands there.

**`getOffsetRange()`** is the way back, for a report of your own. It reads the current text, so after
the rename the call ends a byte later, and **`getFile()`** tells whether a token belongs to this file
at all before its offsets mean anything. On the command line, `vendor/bin/phpsyntax dump file.php
--at=LINE:COLUMN` answers the same question for a person: the innermost node at that place.

## Try it yourself

1. In `lines.php`, insert `$this->sum ??= 0;` before the `return` instead of adding blank lines, with
   `$return->parent->insert(0, ...)`, and check that the return moves to line 7 while `$token->line`
   stays 6.
2. Print the ten widest lines of one of your own source files.
3. Walk from the first token to the last and print only tokens where `startsLine()` is true; you have
   just listed the first token of every line.

## Further reading

- [internals](../../docs/internals.md) - how the index is kept up to date
- [mutation/](../mutation) - edits that the index follows
