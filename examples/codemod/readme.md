# A complete codemod: migrating a deprecated PHP API across a file

Everything in the earlier chapters, used together for the job people actually reach for a parser to do:
rewrite calls of an API that is going away, skip what cannot be rewritten safely, and hand the result to
a reviewer who has better things to do than diff whitespace.

**You will learn:**

- how the pieces from the earlier chapters combine into one working migration
- how a codemod decides what it must refuse to touch
- why a moved node needs its edge trivia cleared, and where whitespace really belongs
- how to steer a traversal, and what happens to a node you rewrite mid-walk

```shell
php examples/codemod/deprecated-api.php
php examples/codemod/traverser.php
```

## Rewriting db_query($db, $sql) into $db->query($sql) across a file (deprecated-api.php)

```
line 16: left alone, the call does not give the two arguments the method takes
line 18: left alone, the call does not give the two arguments the method takes
line 19: left alone, there is a comment inside the call
3 calls migrated

- 		$rows = db_query($db, 'SELECT * FROM orders ORDER BY id DESC');
+ 		$rows = $db->query('SELECT * FROM orders ORDER BY id DESC');
- 		$page = db_query(
- 			$db,
- 			'SELECT * FROM orders LIMIT ' . $limit,
- 		);
+ 		$page = $db->query('SELECT * FROM orders LIMIT ' . $limit);
- 		$named = db_query(query: 'SELECT 1', connection: $db);
+ 		$named = $db->query('SELECT 1');
```

Fifty lines of program, and every part of it comes from a chapter you have already read.

**Finding the calls** is `find(FunctionCallNode::class)`, and deciding that a call is *the* function is
`isGlobalFunctionCall($call, 'db_query')`, which respects the namespace and the imports of the file
instead of matching a string.

**Reading the arguments** is `findArgument()`, which answers the question the rewrite actually has:
which argument a parameter gets. The call that names its arguments is therefore migrated like the ones
that do not, because `db_query(query: 'SELECT 1', connection: $db)` is the same call written another
way, and a codemod that went by the order on the page would have swapped them.

**Deciding what not to touch** is the half of a codemod that a regular expression cannot do at all, and
it is longer than the rewriting half. Three calls are refused here. One passes only a connection, so it
is not the signature being migrated. One unpacks an array, and what the array holds is not known here,
so neither is which argument is which. One has a comment between the arguments, and the rewrite joins
the call onto a single line, so `hasComment()` decides against it. A tool that rewrites what it does not
understand is worse than no tool.

**Building the replacement** is a fragment, `$db->query($sql)`, with the real expressions written into
its slots, the receiver into `object` and the query into the argument's `value`. Both are cloned, because
the old call stays where it is until the replacement takes its place, and both have their edge trivia
cleared, because they carry the whitespace of the place they came from. That one call,
`setEdgeTrivia([], [])`, is the thing to remember from this chapter: **whitespace belongs to a place,
not to a node.**

**Reporting** uses `originalLine`, not `getStartLine()`. The message is about the file on disk, and by
the time the refused calls are examined, two rewrites have already moved the lines of the tree. Both
numbers are correct; they answer different questions.

And what the diff does not contain: the comment about the `ORDER BY`, the blank lines, the tabs, the
formatting of the untouched `return`. On a real code base that is the difference between a pull request
somebody reviews and a pull request somebody closes.

## Walking the tree with enter, leave and a rewrite in flight (traverser.php)

```
FileNode
  NodeList
    FunctionNode
...
string outside the closure: ,
not descending into the closure

rewrites: 1
- 	$sep = ',';
+ 	$sep = self::SEPARATOR . ',';
```

`find()` covers most needs; `Traverser` is what you want when the walk itself has to make decisions.

- `enter` and `leave` see nodes **and tokens**, so a walk can collect the text of a subtree, not only
  its nodes.
- Returning `DontTraverseChildren` keeps the walk out of a subtree, here out of a nested closure, which
  is exactly the shape of a rule that must not look inside a nested scope.
- Returning `StopTraversal` ends the walk.
- A callback may replace or remove the node it was handed. The walk does not descend into the
  replacement, and siblings that were detached meanwhile are skipped rather than crashing the walk.

The last point is the one to keep. The rewrite above replaces `','` with `self::SEPARATOR . ','`, and
the replacement **contains a string the same callback matches**. The run reports one rewrite, not an
infinite regress, because a replacement is not walked. Write that rule by hand over a token array and
you get either a loop or a flag you have to remember to check.

One consequence worth knowing: every node the walk enters it also leaves, the replaced one included,
so a callback keeping a depth counter or a stack across `enter` and `leave` stays right while it
rewrites. The node handed to `leave` is then one that no longer stands in the tree.

## Try it yourself

1. Make the codemod handle the one-argument call by writing `$db->query()` with no arguments and see
   how the report changes.
2. Run the codemod over a real file of your own: read it, parse it, rewrite it, and write the result
   back only when `(string) $file !== $code`.
3. In `traverser.php`, return `StopTraversal` from the first `StringNode` and confirm the walk ends.

## Further reading

- [mutation/](../mutation) - the writes this codemod is built from
- [analyses/](../analyses) - `isGlobalFunctionCall()` and the rest of name resolution
