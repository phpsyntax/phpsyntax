<?php declare(strict_types=1);

namespace PhpSyntax;


enum TriviaKind
{
	case Whitespace;
	case EndOfLine;
	case Comment;
	case DocComment;
	case OpenTag;
}


/**
 * The visibility a member or a promoted parameter declares.
 */
enum Visibility
{
	case Public;
	case Protected;
	case Private;
}
