# The files that break naive PHP tools: BOM, CRLF, close tags, heredocs

A parser is judged on the files nobody writes on purpose. This chapter runs the round trip over eight of
them, and then asks the tree the questions a tool has to ask about each.

**You will learn:**

- which real-world file shapes are handled, and that none of them is a special case in your code
- how a BOM, a hashbang and inline HTML appear in the tree
- why `<?php` is trivia while `?>` is a token, and what follows from that
- how to keep a file's line endings when you insert into it

```shell
php examples/edge-cases/lossless.php
```

## Round-tripping BOM, hashbang, CRLF, inline HTML and __halt_compiler (lossless.php)

```
byte order mark              round trip ok
hashbang line                round trip ok
CRLF line endings            round trip ok
no trailing newline          round trip ok
close tag and HTML           round trip ok
heredoc with interpolation   round trip ok
data after halt              round trip ok
trailing whitespace          round trip ok

the text before <?php is only a BOM and a hashbang: true
the close tag stands in for a semicolon: true
and it keeps the newline PHP swallows: "?>\n"
prevailing line ending of a CRLF file: "\r\n"
```

Every one of those is a real bug report against some tool. A byte order mark that gets doubled or eaten.
A CLI script whose hashbang line ends up inside the code. A CRLF file that comes back mixed. A file
without a trailing newline that grows one, or a file with three that loses two. A template where `?>`
loses the newline PHP swallows after it, quietly changing the output of the page.

Here they are not special cases in the tool, they are ordinary parts of the tree:

- **A BOM and a hashbang are inline HTML**, exactly as PHP sees them, and `InlineHtmlNode::isPreamble()`
  says when that text is only a preamble, which is what makes a file "pure PHP" in the sense a tool
  usually means.
- **`<?php` is not a token at all.** It is `OpenTag` trivia in front of the first token, carrying its own
  whitespace, which is why a rewrite cannot lose it, and why a node cloned from elsewhere in the file
  must have its edge trivia cleared before it is inserted.
- **`?>` is a token**, it stands in for a semicolon (`isSemicolon()` says so), it keeps the newline PHP
  swallows after it, and after a terminated statement it forms an `EmptyStatementNode` of its own.
- **Line endings are yours to preserve.** `Style::detectEol()` reads the prevailing one out of the
  source, so a tool that inserts a line into a CRLF file inserts a CRLF.
- **Whitespace that PHP counts as part of a token stays in the token's text**: inline HTML, the content
  of an interpolated string, heredoc delimiters with their indentation, and a cast written `( int )`.
  Nothing outside the token pretends to own it.

The last of those is the rule that keeps the whole thing consistent. Everything else in the library
follows from *where a piece of text lives*, and every piece of text lives in exactly one place.

## Try it yourself

1. Add your own worst file to `$cases`, one you know has hurt a tool before.
2. Parse a template full of `?>` and `<?=` and print the statement classes; the echo tag is an
   `EchoNode` whose keyword is the open tag.
3. Run the round trip over a directory of plain PHP templates (`.phtml`), the ones with HTML
   between every statement.

## Further reading

- [parsing/](../parsing) - the round-trip invariant itself
- [internals](../../docs/internals.md) - the exact trivia rules for each of these cases
