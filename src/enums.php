<?php declare(strict_types=1);

/**
 * This file is part of the PhpSyntax, a lossless syntax tree for PHP (https://phpsyntax.deegee.dev)
 * Copyright (c) 2026 David Grudl (https://davidgrudl.com)
 */

namespace PhpSyntax;


enum NameForm
{
	case Unqualified;
	case Qualified;
	case FullyQualified;
	case Relative;
}


/**
 * Which of the three tables of names PHP keeps a symbol in.
 */
enum SymbolKind
{
	/** a class, an interface, a trait, an enum and a namespace, which PHP does not tell apart */
	case ClassLike;
	case Function;
	case Constant;


	/** What the `function` or `const` of a `use` statement or of one of its items stands for; without one it is `ClassLike`. */
	public static function fromKeyword(?Token $keyword): self
	{
		return match ($keyword?->id) {
			Token::Function => self::Function,
			Token::Const => self::Constant,
			default => self::ClassLike,
		};
	}
}


/**
 * What is written after an expression and reaches into it.
 */
enum DereferenceKind
{
	/** `->`, `?->` and `[ ]`, the call of a method included */
	case Fetch;

	/** `( )` */
	case Call;

	/** `::` */
	case StaticAccess;
}


/**
 * Which side an operator leans to, where an operand of the same precedence may stand without parentheses.
 */
enum Associativity
{
	/** `$a - $b - $c` is `($a - $b) - $c` */
	case Left;

	/** `$a ?? $b ?? $c` is `$a ?? ($b ?? $c)` */
	case Right;

	/** `$a < $b < $c` is no code */
	case None;
}


/**
 * What a callback of `Traverser::traverse()` tells the walk.
 */
enum TraverseAction
{
	/** keeps the walk out of the children of the node */
	case SkipChildren;

	/** ends the walk; the nodes already entered are left as it unwinds */
	case Stop;
}


/**
 * What a slot is to the indentation of the lines its node spreads over, relative to the line the node
 * begins on; the level each role stands for is left to whatever lays the code out.
 */
enum LayoutRole
{
	/** what the construct holds: items, statements, members, operands continuing it */
	case Content;

	/** stands where the construct stands: an opening brace, a keyword continuing it, its head under attributes */
	case Anchor;

	/** closes the construct; a comment above it belongs to the content before it */
	case Closer;

	/** the body of a structure: a block puts its brace where the structure stands, a bare statement steps in */
	case Body;

	/** a binary operator and what it applies to */
	case Operator;

	/** the branches of a ternary and the operators opening them */
	case Branch;

	/** a link of a chain of calls or property accesses */
	case Link;

	/** the cases of a `switch` */
	case Case;
}


enum Visibility
{
	case Public;
	case Protected;
	case Private;
}
