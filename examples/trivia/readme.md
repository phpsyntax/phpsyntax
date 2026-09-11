# Comments and whitespace in a PHP syntax tree: trivia on tokens, not attributes on nodes

An abstract syntax tree keeps the meaning of your code and hangs comments off the nearest node; a
**lossless concrete syntax tree** keeps the code, and every space and comment belongs to a token, under
one rule you can hold in your head.

**You will learn:**

- the one rule that decides which token a piece of whitespace belongs to
- how to read a doc comment, and how to ask whether a change would destroy one
- how to write spacing, blank lines and indentation without breaking the file
- why those helpers exist instead of you writing `"\n\t"` into an array

```shell
php examples/trivia/comments.php
php examples/trivia/whitespace.php
```

**The rule:** what follows a token up to and including the end of its line is that token's *trailing*
trivia. Everything else, blank lines, indentation, comments standing on their own line, is the *leading*
trivia of the next token. Three things sit outside it: `<?php` is leading trivia and never a token; the
end-of-file token has only leading trivia, and carries whatever stands after the last line ending; and a
token whose own text ends a line (`?>`, the start of a heredoc) closes its line by itself, which is what
the `null` in the run below is about.

That single rule is what makes questions like "does this statement end its line" answerable, and it is
why moving a statement moves its comment with it instead of orphaning it.

## Reading, keeping and removing comments in PHP code (comments.php)

Reading comments, asking whether one is in the way, and removing one.

```
doc comment text:
Total price including VAT.
@param  list<Item>  $items

inside the method: Comment "// rounding: the invoice is what the customer pays"
inside the method: Comment "# legacy hash comment"

the class body holds a comment: true
between "return" and the closing brace: true

- 		// rounding: the invoice is what the customer pays
```

`getDocComment()` finds the doc block of a node whether it sits on the lines above it or after the
previous token on the same line, and `getCommentText()` hands over the text without `/**`, without the
stars that open each line and without the trailing spaces the tokenizer counts as part of a `//`
comment. What the text *means* is yours to parse; the tree carries it, it does not interpret it.

The two queries in the middle are what keeps a refactoring tool from destroying something:

```php
$node->hasComment();                 // is there a comment anywhere inside this node?
$token->hasCommentUpTo($otherToken); // is there one between these two tokens?
```

`hasComment()` walks the tokens of the node itself, so it answers the same inside a file and on a
fragment or a clone you are still assembling. `hasCommentUpTo()` spans two tokens and therefore needs
the file.

Any tool that rewrites code has to answer them, because deleting a construct with a comment in it
destroys something a human wrote on purpose. Written by hand over a flat token array, each is a loop
with edge cases; here it is a method call, tested once, in the library.

The removal at the end shows the other half of the deal. One line removed, nothing else touched: a
comment alone on its line takes the line with it, an inline one takes one adjacent space. The whole
diff of the program is that one line.

Note where the removal is called: `$method->removeTrivia($comment)`. A `Trivia` does not know its token,
so the node finds it, which means a comment can be removed exactly where it was found. The same pair
exists on the token (`Token::removeTrivia()`, `replaceTrivia()`) for when you already hold one, and
`removeDocComment()` is the special case of the same thing.

## Whitespace as data: a small PHP code formatter (whitespace.php)

Whitespace is data you can read and write, not a formatting pass you have to trigger.

```
space before "+": ""
space after "+": ""
space after the class brace: null
indentation of "return": "\t\t\t"
indentation of "+": ""
line indentation of "+": "\t\t\t"

--- the method put back where it belongs ---
- 		public function count(): int
- 		{
- 			return count($this->rows) + 1;
- 		}
+ 	public function count(): int
+ 	{
+ 		return count($this->rows) + 1;
+ 	}

<?php
class Report
{
	public function rows(): array
	{
		return $this->rows;
	}

	public function count(): int
	{
		return count($this->rows) + 1;
	}
}
```

`getTrailingSpace()` reads the horizontal space between a token and the next one on its line. It
returns `null` rather than lying when a line ending, a comment or string content is in the way, which is
exactly what you want a spacing rule to notice, and `setTrailingSpace()` writes it back.

Indentation is a property of a line, not of a token, and the two getters keep that straight.
`getIndentation()` is what stands before the token when the token starts a line, and an empty string
when it does not, which is why `+` in the middle of a line answers with nothing. `getLineIndentation()`
walks back to the token that does start the line and answers for both. Reach for the first when you are
about to write indentation on that very token, and for the second when you need to know how deep the
code around you sits.

The rest of the run is a formatter of a dozen lines. A blank line between two methods is
`setBlankLinesBefore(1)`. Breaking a one-line method body across lines is `ensureLeadingNewline()` plus
`setIndentation()`, once for the opening brace, once for the statement and once for the closing brace.
And **nothing else in the file moves**: the sample keeps its tabs and the file still ends the way it
ended.

The line ending comes from `Style::detectEol($code)` rather than from a constant in the tool, which is
how the same script edits a CRLF file without turning it into a mixed one. That single argument is the
difference between a formatter people can run on Windows and one they cannot.

Why those helpers rather than writing `"\n\t"` into some trivia array yourself? Because the trivia have
to stay canonical, the line ending belongs to the trailing trivia of the token that ends the line, and a
misplaced one makes `getTrailingSpace()` blind. `ensureLeadingNewline()` puts it where the lexer would
have. Build on it and your tool behaves like the parser, not like a string replacement.

The last step is the one a hand-written loop gets wrong. The second method came in indented a level
too deep, and `Indentation::shift($node, -1, $style)` puts it back: every line the node opens moves,
and where the node holds a heredoc its body and closing delimiter move with it, so the string the
heredoc stands for is the same afterwards. Miss that and a formatter quietly rewrites data.

`Style` carries the conventions of a file: the indentation unit, the line ending and the tab width. It
is the one object a formatting tool passes around, and the library itself has no opinion about what is
in it.

## Try it yourself

1. In `comments.php`, remove the doc comment with `removeDocComment()` instead and watch the diff.
2. Make `whitespace.php` take the indentation from `Style(indent: '    ')` and produce spaces.
3. Ask `getTrailingSpace()` on a token followed by a comment and confirm you get `null`.

## Further reading

- [internals](../../docs/internals.md) - the exact trivia rules, including interpolation and heredocs
- [mutation/](../mutation) - the same helpers in a real rewrite
- [positions/](../positions) - lines and columns that follow your edits
