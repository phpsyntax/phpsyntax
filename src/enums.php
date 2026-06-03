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
