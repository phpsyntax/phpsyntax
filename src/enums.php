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
 * Form of a name as written in the source.
 */
enum NameKind
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


	/** What the type token of a use statement or of one of its items stands for; without one it is ClassLike. */
	public static function fromUseType(?Token $type): self
	{
		return match ($type?->kind) {
			TokenKind::Function => self::Function,
			TokenKind::Const => self::Constant,
			default => self::ClassLike,
		};
	}
}


/**
 * What an unqualified name reaches where it stands, `NameResolver::getUnqualifiedResolution()`: PHP looks a function
 * or a constant up in the namespace first and falls back to the global one, which a file alone cannot tell apart.
 */
enum UnqualifiedResolution
{
	/** a symbol of the global namespace for certain: in the global namespace, through an import of a global name, or where the namespace is known not to declare the name */
	case Global;

	/** the global symbol, unless the namespace declares one of the name outside the file */
	case Uncertain;

	/** a symbol of a namespace for certain: one the namespace declares, one an import brings in, or a class, which does not fall back */
	case Namespaced;
}


/**
 * What is written after an expression and reaches into it: `ExpressionNode::getAccessKind()` says which
 * of the three the parent is, `isDereferenceable()` what may stand there bare, which differs by the kind.
 */
enum AccessKind
{
	/** -> or ?-> a property, [ ] an element: they take any expression */
	case Member;

	/** ( ) a call, which takes a name or a member written before it for its own */
	case Call;

	/** :: a constant, a static member or class, which takes the name of a class on its left */
	case ClassName;
}


/**
 * What happens to the comments inside a removed subtree.
 */
enum CommentPolicy
{
	case MoveToNextToken;
	case MoveToPreviousToken;
	case Drop;
}


/**
 * What a slot is to the indentation of the lines its node spreads over, relative to the line the node
 * begins on; the level each role stands for is the business of the style.
 */
enum LayoutRole
{
	/** what the construct holds: items, statements, members, operands continuing it */
	case Content;

	/** stands where the construct stands: an opening brace, a keyword continuing it, its head under attributes */
	case Anchor;

	/** closes the construct; a comment above it belongs to the content before it */
	case Closes;

	/** the body of a structure: a block puts its brace where the structure stands, a bare statement steps in */
	case Body;

	/** a binary operator and what it applies to */
	case Operator;

	/** the branches of a ternary and the operators opening them */
	case Branch;

	/** a link of a chain of calls or property accesses */
	case Link;

	/** the cases of a switch */
	case Case;
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
