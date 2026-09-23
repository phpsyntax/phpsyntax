# Migrating from nikic/php-parser

How to move a tool from an abstract syntax tree to this concrete one: what maps onto what, what the tree
now gives you that you used to reconstruct, and what PhpSyntax does not do at all. Read
[docs/reference/nodes.md](reference/nodes.md) beside it; this page is the translation, that one is the catalog.

Most of the work is deleting. A codemod on php-parser carries a layer that exists only because the AST
cannot give the file back: original tokens kept next to the tree, replacements collected by byte offset,
a pretty printer told to preserve formatting on a best-effort basis. None of that has a counterpart here,
because printing the tree is the file. Expect the diff to be mostly red.

## Read this first

**The tree is the file.** Every token, every space and every comment is in it, `Printer::print()` gives the
source back byte for byte, and a node prints as it was written. So the question "how do I get the text of
this node" has an answer that is always right (`$node->text`), and the question "how do I patch the source
at this offset" has no answer, because you no longer patch the source.

**Tokens are children.** `(`, `;` and `}` sit in named slots like any other child, `getChildren()` returns
them in source order, and a walk over a subtree sees them. Code written against php-parser assumes children
are nodes; that assumption breaks here, usually at `getChildren()` and in a recursive walk.

**Analyses ask, they do not rewrite.** php-parser's `NameResolver` is a visitor that mutates the tree and
leaves `namespacedName` attributes behind, so name resolution is a pass you run before anything else and
an order you have to respect. Here `Analyses\NameResolver` is an object you query, the tree is untouched,
and there is no order to get wrong.

**Parentheses are nodes, and writing them is your job.** php-parser drops them; `(1 + 2) * 3` and the tree
of `1 + 2 * 3` differ only in shape, and the pretty printer puts back whatever the grammar needs. Here `($a)`
is a `ParenthesizedNode` with `isRedundant()` to ask whether it may go, printing adds nothing, and an
expression written into an operand slot is written as it stands. Code that assumed an expression is never
wrapped will find a wrapper, and code that leaned on the printer for precedence has a method to lean on
instead, see [Changing code](#changing-code).

## What PhpSyntax does not do

Stated as facts, so nobody discovers them at the wrong moment.

- **No error recovery.** Source the grammar refuses throws a `ParseException` and there is no partial tree.
  `ErrorHandler\Collecting`, `Expr\Error` and the recovery productions have no counterpart.
- **The syntax it reads is that of PHP 8.5, and only that.** There is no `ParserFactory::createForVersion()`
  and no `PhpVersion`. A file written for an older PHP parses only where the syntax is still valid today.
  Two concrete consequences for code older than PHP 8.0: a curly string offset (`$s{0}`) is refused by the
  grammar, and `#[TODO]`, a comment in PHP 7, is an attribute in PHP 8 and the file around it fails to parse
  or parses as something else. (Removals the compiler rather than the lexer enforces, `(real)` and `(unset)`,
  still parse, because `PhpToken::tokenize()` still emits them.)
- **No pretty printer.** Nothing reprints a tree in a style of its own. What you print is what was written,
  plus what you changed. There is no `prettyPrint()`, no `prettyPrintFile()`, no `printFormatPreserving()`.
- **No builders.** `BuilderFactory` and the `Builder\*` classes have no counterpart. A new node is made by
  parsing its text: `parseExpression()`, `parseStatement()`, `parseType()`, `parseName()`, `parseFragment()`.
  What is put together out of nodes you are already holding, which no text can carry, has a factory `of()`
  on the class: `FunctionCallNode`, `MethodCallNode`, `StaticMethodCallNode`, `NewNode`, `ArgumentListNode`,
  `BinaryOpNode` and `ParenthesizedNode`.
- **No node dumper.** There is no `NodeDumper`, and no JSON serialization of a tree.
- **Constant expressions are read only where they are written as values.** `ExpressionNode::toValue()` reads
  literals, arrays of literals, a parenthesized literal and a sign in front of a number.
  `ConstExprEvaluator`, which folds `1 + 2` and hands a constant fetch to an evaluator you supply, has no
  counterpart.
- **No semantics.** What a class inherits, what type an expression has, which method a call reaches: not here,
  and not in php-parser either, but worth repeating because the name resolution below is easy to mistake for it.

## Setup

```shell
composer remove nikic/php-parser
composer require phpsyntax/phpsyntax
```

Until the first release there is no stable version to resolve, so a project with the default
`minimum-stability: stable` needs the constraint written out: `"phpsyntax/phpsyntax": "dev-master"`.

PHP 8.4 to 8.6, `ext-tokenizer`, no package dependencies. Drop every `class_exists()` guard around a node
class that only exists on a newer parser or a newer runtime: the grammar is one grammar, so property hooks
and asymmetric visibility parse wherever the library runs.

## Parsing

```php
// php-parser
$parser = (new ParserFactory)->createForNewestSupportedVersion();
$stmts = $parser->parse($code);          // ?array, null on error with a handler
$traverser = new NodeTraverser;
$traverser->addVisitor(new NodeVisitor\ParentConnectingVisitor);
$traverser->addVisitor(new NodeVisitor\NameResolver);
$stmts = $traverser->traverse($stmts);

// PhpSyntax
$file = new Parser()->parse($code);      // FileNode, or a ParseException
$resolver = new NameResolver($file);
```

`parse()` returns a `FileNode`, not an array: a file has a root. Parents are set by the tree itself, so
`ParentConnectingVisitor` and `NodeConnectingVisitor` are gone, and `$node->parent` is always there.
`ParseException` carries `$sourceLine`, `$sourceColumn` and `$sourceOffset`, which are the position in the
parsed code, not the `getLine()` of the exception.

## Finding nodes

| php-parser | PhpSyntax |
|---|---|
| `NodeFinder::find($nodes, $filter)` | `$node->find(Class::class, $predicate)` |
| `NodeFinder::findInstanceOf($nodes, $class)` | `$node->find(Class::class)` |
| `NodeFinder::findFirst($nodes, $filter)` | `$node->findFirst(Class::class, $predicate)` |
| `NodeFinder::findFirstInstanceOf()` | `$node->findFirst(Class::class)` |
| `NodeTraverser` + a `NodeVisitor` | `new Traverser()->traverse($root, $enter, $leave)` |
| `NodeVisitor::DONT_TRAVERSE_CHILDREN` | `Traverser::DontTraverseChildren` |
| `NodeVisitor::STOP_TRAVERSAL` | `Traverser::StopTraversal` |
| `NodeVisitor::REMOVE_NODE` | `$node->remove()`, never from inside a `Traverser` walk |
| `$node->getAttribute('parent')` | `$node->parent` |
| walking up by hand | `$node->findAncestor(Class::class)` |

`find()` takes one class or interface, and it returns a snapshot, so removing or replacing the nodes it gave
you while you iterate them is fine. That is the place to rewrite from; the traverser walks the live tree and
is for reading. It visits tokens as well as nodes, so a callback that assumes a node must say so.

There is no visitor object and no `beforeTraverse`/`afterTraverse`: a walk is a closure, and state that a
visitor used to hold in a property lives in the closure or in your own object.

## Names, and what a name means

This is where the most code disappears, and where a migration goes wrong most easily.

| php-parser | PhpSyntax |
|---|---|
| `Node\Name`, `Name\FullyQualified`, `Name\Relative` | one `NameNode`, `$kind` says which (`NameKind`) |
| `$name->toString()` | `$name->text` (as written), `$name->parts`, `$name->shortName` |
| `$name->toCodeString()` | `'\\' . $resolver->resolveClass($name)` |
| `$node->namespacedName` on a declaration | `$resolver->getDeclaredName($node)` |
| find a declaration by its fully qualified name | `$resolver->findDeclaration($fqn, SymbolKind::ClassLike)` |
| `NameResolver` rewrote the node to `FullyQualified` | `$resolver->resolveClass($name)`, tree untouched |
| `getAttribute('originalName')` | the node still is the original name |
| guess the kind from the parent (`ConstFetch`, `FuncCall`) | `$name->role` (`SymbolKind`) |
| write the short name back by hand | `$resolver->getShortName($fqn, $kind, $at)` |
| `Node\Identifier` | `IdentifierNode`, `$text` |
| `Node\VarLikeIdentifier` | a `Token`; read `$item->plainName` |

Three things to internalize:

**`$role` says which table of names the name belongs to, not whether it refers to a symbol.** A name in a
`namespace` statement or a class import has the role of a class, because PHP keeps namespaces and classes in
one table, and so does `int` in a type position, because a type is written as a name. Ask `isReference()` before you
resolve. `resolveClass()`, `resolveFunction()` and `resolveConstant()` throw an `InvalidArgumentException`
on a name that refers to nothing, which is deliberate: silently resolving `int` inside a namespace gives you
`Foo\int`, and that is the kind of wrong answer that reaches your output without ever failing.

```php
foreach ($file->find(NameNode::class) as $name) {
    if (!$name->isReference()) {
        continue;                       // a use item, a namespace, self/static/parent, a builtin type
    }
    $fqn = match ($name->role) {
        SymbolKind::ClassLike => $resolver->resolveClass($name),
        SymbolKind::Function => $resolver->resolveFunction($name),
        SymbolKind::Constant => $resolver->resolveConstant($name),
    };
}
```

**An unqualified function or constant may not be resolvable at all.** PHP falls back to the global symbol at
runtime when the namespace declares none, and whether it does is a fact about other files.
`getUnqualifiedResolution()` tells `Global`, `Namespaced` and `Uncertain` apart, and a tool that rewrites
names has to leave the uncertain ones as written. php-parser is right to leave such a name alone, it just
does not tell you which of the three cases you are in, so code that treated it as global was guessing.

**`use A\{B, C}` is the same node as `use A\B`.** php-parser kept `Stmt\GroupUse` apart from `Stmt\Use_`, and
a tool matching only the latter dropped group imports without noticing. Here both are `Statement\UseNode`,
`UseItemNode::$fullName` carries the prefix, `$kind` carries the per-item `function`/`const`, and
`UseNode::isGroup()` tells them apart when you care.

## The node map

Only what differs in more than the suffix. Everything else keeps its name with `Node` appended:
`Stmt\Return_` is `Statement\ReturnNode`, `Expr\Isset_` is `Expression\IssetNode`, and so on. Where
php-parser 5 renamed a class and kept the old name as a deprecated alias, the current name comes first and
the one your older code is more likely to use is in parentheses.

### Merged in PhpSyntax

| php-parser | PhpSyntax |
|---|---|
| `Stmt\Use_` + `Stmt\GroupUse` | `Statement\UseNode` |
| `Expr\MethodCall` + `Expr\NullsafeMethodCall` | `Expression\MethodCallNode`, `isNullsafe()` |
| `Expr\PropertyFetch` + `Expr\NullsafePropertyFetch` | `Expression\PropertyFetchNode`, `isNullsafe()` |
| `Expr\BinaryOp\*` (a class per operator) | `Expression\BinaryOpNode`, the operator is a token |
| `Expr\AssignOp\*` | `Expression\CombinedAssignmentNode` |
| `Expr\Cast\*` | `Expression\CastNode` |
| `Expr\UnaryMinus`, `UnaryPlus`, `BooleanNot`, `BitwiseNot`, `ErrorSuppress` | `Expression\UnaryOpNode` |
| `Expr\PreInc`, `PreDec` | `Expression\PrefixOpNode` |
| `Expr\PostInc`, `PostDec` | `Expression\PostfixOpNode` |
| `Scalar\MagicConst\*` | `Scalar\MagicConstantNode` |

Matching on a class is therefore matching on a class plus an operator token. `Token::is()` takes a `TokenKind`
constant or the text of the token, so `$node->operator->is('+')`, `is('=>')` and `is('instanceof')` all work;
`ModifiersNode::has()` takes a `TokenKind` constant.

### Split, renamed or new

| php-parser | PhpSyntax |
|---|---|
| `Stmt\ClassMethod` | `Member\MethodNode` |
| `Stmt\Property` + `PropertyItem` | `Member\PropertyNode` + `Member\PropertyItemNode` |
| `Stmt\ClassConst` + `Node\Const_` | `Member\ClassConstNode` + `ConstItemNode` |
| `Stmt\Const_` + `Node\Const_` | `Statement\ConstNode` + `ConstItemNode` |
| `Stmt\EnumCase` | `Member\EnumCaseNode` |
| `Stmt\TraitUse` | `Member\TraitUseNode` |
| `Stmt\TraitUseAdaptation\Precedence` / `Alias` | `Member\TraitPrecedenceNode` / `TraitAliasNode` |
| `Node\PropertyHook` | `Member\PropertyHookNode` |
| `Stmt\Expression` | `Statement\ExpressionStatementNode` |
| `Stmt\Do_` | `Statement\DoWhileNode` |
| `Stmt\TryCatch` | `Statement\TryNode` |
| `Stmt\InlineHTML` | `Statement\InlineHtmlNode` |
| `Node\DeclareItem` (`Stmt\DeclareDeclare`) | `DeclareItemNode` |
| `Node\StaticVar` (`Stmt\StaticVar`) | `StaticVariableNode` |
| `Node\UseItem` (`Stmt\UseUse`) | `UseItemNode`, `$fullName`, `$kind`, `$alias` |
| `Expr\ArrayDimFetch` | `Expression\ArrayAccessNode` |
| `Expr\StaticCall` | `Expression\StaticMethodCallNode` |
| `Expr\ClassConstFetch` | `Expression\ClassConstantFetchNode` |
| `Expr\ConstFetch` | `Expression\ConstantFetchNode`, but see literals below |
| `Expr\Closure` + `Node\ClosureUse` | `Expression\ClosureNode` + `ClosureUsesNode` + `ClosureUseNode` |
| `Scalar\Int_` (`LNumber`) | `Scalar\IntegerNode` |
| `Scalar\Float_` (`DNumber`) | `Scalar\FloatNode` |
| `Scalar\String_` | `Scalar\StringNode` or `Scalar\HeredocNode` |
| `Scalar\InterpolatedString` (`Encapsed`) | `Scalar\InterpolatedStringNode` or `Scalar\HeredocNode` |
| `Node\InterpolatedStringPart` (`Scalar\EncapsedStringPart`) | `Scalar\InterpolatedStringPartNode`, `Scalar\UnquotedStringNode` |
| `Node\NullableType` / `UnionType` / `IntersectionType` | `Type\NullableTypeNode` / `UnionTypeNode` / `IntersectionTypeNode` |
| a type written as `Identifier` or `Name` | `Type\NamedTypeNode`, `isBuiltin()` |
| `Node\Arg` | `ArgumentNode` |
| `Node\Param` | `ParameterNode` |
| `Node\Attribute` / `AttributeGroup` | `AttributeNode` / `AttributeGroupNode` |
| `Stmt\ClassLike` (abstract class) | `ClassLikeNode` (interface, anonymous classes included) |
| `Node\FunctionLike` | `FunctionLikeNode` (interface) |
| nothing | `Expression\ParenthesizedNode` |
| nothing | `Scalar\BooleanNode`, `Scalar\NullNode` |
| nothing | `NodeList`, `SeparatedNodeList` (a list is a node) |
| nothing | `ModifiersNode` |
| `Stmt\Nop` | nothing; a standalone comment is trivia on the next token |
| `Expr\Error` | nothing; no error recovery |

`Statement\EmptyStatementNode` is a bare `;`, not php-parser's `Nop`.

### Lists are nodes

`$class->members`, `$call->arguments->items` and `$method->parameters` are `NodeList` or `SeparatedNodeList`,
not arrays. They are `Countable` and `IteratorAggregate`, so `foreach` and `count()` work; `getItems()` gives
the plain array where you need one. They also carry the separators, which is what lets `insert()` and
`remove()` keep the style of the list they are written in.

## Modifiers

php-parser has a bitmask on the node plus `isPublic()`/`isStatic()`/... helpers duplicated on every class
that can carry them, and `Param::$flags` for promotion. PhpSyntax has one `ModifiersNode` in a slot.

| php-parser | PhpSyntax |
|---|---|
| `$node->isPublic()`, `isStatic()`, `isAbstract()`, `isFinal()`, `isReadonly()` | `$node->modifiers->isPublic()`, ... |
| `$node->flags & Modifiers::PRIVATE` | `$node->modifiers->visibility` (`?Visibility`) |
| `$node->flags & Modifiers::PRIVATE_SET` | `$node->modifiers->writeVisibility` |
| `$param->flags !== 0` | `$param->isPromoted()` |
| `$fn->returnsByRef()` | `$fn->ampersand !== null` |
| `$param->byRef`, `$param->variadic` | `$param->ampersand !== null`, `$param->ellipsis !== null` |

A missing visibility is `null`, not `public`; `isPublic()` is the one that treats the default as public.

## Literals

php-parser keeps the meaning in the node and the writing in attributes: `kind`, `rawValue`, `docLabel`,
`docIndentation`. PhpSyntax keeps both halves as typed properties, so the arithmetic over attribute
strings goes away.

| php-parser | PhpSyntax |
|---|---|
| `$node->value` on `Int_`, `Float_`, `String_` | `$node->value` |
| `getAttribute('rawValue')` | `$node->token->text` |
| `getAttribute('kind')` is `KIND_BIN`/`KIND_HEX`/... | `IntegerNode::$base`, the same numbers (2, 8, 10, 16) |
| `getAttribute('kind')` is single or double quoted | `StringNode::$quote` |
| `getAttribute('kind')` is heredoc or nowdoc | `HeredocNode`, `isNowdoc()`, `hasInterpolation()` |
| `getAttribute('docLabel')`, `getAttribute('docIndentation')` | `HeredocNode::$label`, `$indentation` |
| `ConstFetch` named `true`, `false`, `null` | `Scalar\BooleanNode` with `$value`, `Scalar\NullNode` |
| write a literal with the right escapes by hand | `StringNode::fromValue($value, $quote)`, `setValue()` |
| `ConstExprEvaluator` | `$expr->hasValue()`, `$expr->toValue()`, for literals |

`toValue()` is worth reaching for before you compare code as text. Two spellings of one value are one value:
`0b0001` and `1`, `1_000_000` and `1000000`, `'[\\\\/]'` and `'[\\\/]'`, `NULL` and `null`, an array written
on one line and the same array written on five. A tool that compares the written form reports every one of
those as a change.

## Comments

| php-parser | PhpSyntax |
|---|---|
| `$node->getComments()`, the comments *preceding* the node | `$node->leadingTrivia`, filtered by `Trivia::isComment()` |
| nothing | `$node->getComments()`, the comments *inside* the node |
| `$node->getDocComment()` | `$node->getDocComment()`, returns `?Trivia` |
| `$comment->getText()` | `$trivia->text` (the delimiters included) |
| `$comment->getReformattedText()` | `$trivia->getCommentText()` (delimiters and leading `*` stripped) |
| `$node->setDocComment(new Comment\Doc(''))` to consume it | `$node->removeDocComment()` |
| `$node->setDocComment($doc)` | `$node->replaceDocComment(Trivia $doc)`, which throws where there is none |
| a comment belongs to the nearest node | a comment belongs to a token, as leading or trailing trivia |
| nothing | `$node->hasComment()`, `$token->hasCommentUpTo($end)` |

`hasComment()` is the question php-parser leaves you to answer badly: before a rewrite deletes or merges
code, it says whether a human wrote something in there that the rewrite would destroy. Every rewriting tool
needs it, and `remove()` takes a `CommentPolicy` for what to do with a comment it displaces.

## Positions

| php-parser | PhpSyntax |
|---|---|
| `$node->getStartLine()`, `getEndLine()` | `$node->getStartLine()`, `getEndLine()` |
| `$node->getStartFilePos()`, `getEndFilePos()` | `$file->getIndex()->getOffsetRange($node)`, but see below |
| `$node->getStartTokenPos()` | `$file->getIndex()->getIndex($node->getFirstToken())` |
| a position never changes after a mutation | `$token->getLine()` follows your mutations; `originalLine` and `originalOffset` are where it was on disk |
| nothing | `$token->getNext()`, `getPrevious()`, across node boundaries |
| nothing | `$index->findNode($start, $end, $class)` |

**The two libraries end a range differently.** `getEndFilePos()` is the offset of the last character;
`getOffsetRange()` returns `[start, end]` with `end` one past it. So the php-parser idiom
`substr($code, $start, $end - $start + 1)` takes one character too many here, and the right length is
`$end - $start`. Mostly this does not come up, because if you were using offsets to cut text out of the
source, that whole layer goes: see below.

## Printing, and the one thing to get right

```php
Printer::print($node)                  // the node with the trivia on its outer edges
$node->text                            // the node as written, without the outer edges
Printer::printText($node, $substitute) // the same, with a text of your own per token
$node->getTokenTexts()                 // list<string>, what matches() compares
(string) $node                         // Printer::print($node)
```

`prettyPrint()` has no counterpart, and the closest thing to `prettyPrintExpr($expr)` is `$expr->text`, with
one difference that matters: php-parser reprinted the expression in its own style, PhpSyntax gives you what
the file says. Whitespace, comments and a trailing comma are in there.

The substitute, `fn(Token): ?string`, is how a tool shows code with changes it has not made to the tree:
return a text for the tokens you want to replace and `null` for the rest. This is what replaces collecting
replacements by byte offset, sorting them backwards and `substr_replace()`-ing them into a slice of the source.

**Decide which string you compare and which string you measure, and do not mix them up.** For the identity
of a piece of code, use `getTokenTexts()` or `matches()`: they ignore whitespace entirely, so reindenting a
method does not change its identity. For how much code there is, use the length of `$node->text`: it is in
the same units as the source. Using the length of a whitespace-insensitive form as a size is a silent bug,
because joining tokens by a separator makes the string longer than the code by a few percent, and a threshold
tuned on source characters then starts firing differently.

## Changing code

php-parser's answer to a codemod is `printFormatPreserving($newStmts, $origStmts, $origTokens)`: it needs the
old tree and the old tokens kept next to the new one, and its documentation says it "works on a best-effort
basis and may sometimes reformat more code than necessary". Here a change is a write into a slot, and the
file it prints is the file it parsed with that one change in it.

```php
$if->condition = $parser->parseExpression('$order["items"] === []');
$call->replaceWith($replacement);
$parameters->insert(2, $parameter);
$statement->remove(CommentPolicy::MoveToNextToken);
$parenthesized->replaceWith($parenthesized->expression);
```

A list clones the separator style already in use, gives a new item the indentation of its neighbour and leaves
a trailing comma trailing. `remove()` takes the whole line when the node had a line to itself, and the
separator that went with it.

`$file->revision` counts the changes, so a tool can write the file only when it touched it. And when you want
the source untouched, do not mutate at all: collect a replacement per token and hand it to
`Printer::printText()`. That keeps every analysis reading the file as it was parsed.

### Building the replacement

`new Node\Expr\MethodCall($conn, 'query', [new Node\Arg($sql)])` has two counterparts here, and which one
you want depends on where the pieces come from. Text you write yourself is a fragment,
`parseExpression('$db->query($sql)')`. Pieces that are already nodes, because you took them out of the code
you are rewriting, go to a factory, because no string can hold a node:

```php
MethodCallNode::of($conn->withoutEdgeTrivia(), 'query', ArgumentListNode::of($sql->withoutEdgeTrivia()));
```

`withoutEdgeTrivia()` has no php-parser counterpart, and it is the habit to acquire. In an AST a node carries
no whitespace, so it may be written anywhere; here it carries the whitespace of the place it was written in,
and that whitespace belongs to the place, not to the node. A plain `clone` brings the indentation of the old
line along, and for the first statement of a file it brings the `<?php` tag, which is then printed twice.

### Precedence, which the pretty printer used to hide

Building the tree you mean was enough in php-parser: the printer wrote something that parses back to it.
Printing here is concatenation, so the expression you write into an operand slot is the expression that
stands there.

```php
// $s = ucfirst($a ?? $b) . 'x';   and the call is to go, its argument staying
$call->replaceWith($argument);            // $s = $a ?? $b . 'x';     another program
$call->replaceWithExpression($argument);  // $s = ($a ?? $b) . 'x';   what you meant
```

`replaceWithExpression()` writes the expression in parentheses and takes them away again where
`isRedundant()` calls them needless, which is as close as this library comes to what the printer did for you.
Reach for `replaceWith()` where the place is fenced by delimiters, an argument, a match arm, the operand of
a `return`, and for `replaceWithExpression()` wherever the place is an operand of an operator or is reached
into by `->`, `[]`, `()` or `::`. The `of()` factories apply the same rule to what a call is made on, so one
handed a `new Foo` writes `(new Foo)->bar()`. Parentheses you want for the reader rather than for the grammar
are written on purpose, with `ParenthesizedNode::of()`.

One more seam is handled for you: after the swap `replaceWith()` asks the lexer whether the tokens it has
brought together may stand side by side and puts a space where they may not, `.` against `119` otherwise
being the number `0.119`. `Lexer::canAdjoin()` is that question for when you are the one taking whitespace
away.

Questions worth asking before a rewrite, none of which php-parser answers:

```php
$a->matches($b);               // same code, whatever the whitespace
$expr->isRepeatableRead();     // is reading it twice free of side effects
$node->hasComment();           // would the rewrite destroy something a human wrote
$parenthesized->isRedundant(); // may these parentheses go
$call->arguments->findArgument('object', 0); // the argument a parameter gets, named or not
```

## Traps

- **Resolving a name that refers to nothing.** `$role` is `ClassLike` by default, so `int`, `string` and a
  name in a `namespace` statement all land there. Guard with `isReference()`; the resolver throws otherwise.
- **`true`, `false` and `null` are not constant fetches.** Code matching `ConstFetch` by name has to match
  `BooleanNode` and `NullNode` instead.
- **Nullsafe calls are ordinary calls.** Code that matched `MethodCall` and forgot `NullsafeMethodCall` was
  quietly missing half the calls; here it stops missing them, which can change what your tool reports.
- **A walk sees tokens.** `getChildren()` and `Traverser` hand you `Token` as well as `Node`.
- **A property item carries the dollar.** `PropertyItemNode::$name` is a `T_VARIABLE` token, because the tree
  is lossless; `$item->plainName` is the name without it, and so are `VariableNode::$plainName` and
  `StaticPropertyFetchNode::$plainName`.
- **One declaration, several members.** `public $a, $b;` is one `PropertyNode` with two items, and so is
  `const A = 1, B = 2;`. The doc comment belongs to the declaration: `PropertyItemNode::getDocComment()`
  returns `null`. So a loop over the items that reads the comment off the declaration gets the same comment
  once per item, and has to remember which one it already handed out; an `SplObjectStorage` keyed by the
  declaration does it.
- **`getDocComment()` looks at the first token of the node**, which is the `#[` of its first attribute when
  it has any. A doc comment written above the attributes is therefore found, not skipped.
- **An anonymous class is a `ClassLikeNode` too**, with `$name === null`, and `getDeclaredName()` returns
  `null` for it.

## A worked migration

The move of a tool that reads a PHP file and rebuilds it as an object model of classes and functions, method
bodies included, is a fair sample of the shape of this work:

- Four private methods went away entirely. They existed to cut text out of the source by byte offset
  and patch it. They are replaced by collecting a replacement text per token and calling
  `Printer::printText()`, and the tree is never mutated, so the analyses keep reading the file as parsed.
- The `class_exists(Node\PropertyHook::class)` branches went away: one grammar, so hooks and asymmetric
  visibility always parse.
- The arithmetic over `docLabel` lengths and `docIndentation` went away in favour of `HeredocNode::$label`
  and `$indentation`.
- Guessing a name's kind from its parent node went away in favour of `$role`, and the lookup of a declaration
  by fully qualified name went away in favour of `findDeclaration()`.
- Group `use` started working. It never did before: the old code matched `Stmt\Use_` and php-parser kept
  `Stmt\GroupUse` apart, so `use A\{B, C};` was silently dropped.

Two behaviour changes came with it, both worth expecting in your own port: a multi-line string folded onto
one line is now escaped by the library rather than by `addcslashes()`, which escapes more; and a heredoc
without interpolation is read as a value instead of being preserved as written, so it comes back as a quoted
string. Neither is a bug in either library. They are the difference between reproducing what was written and
reproducing what it means, and a port has to decide which one it wants at each point.
