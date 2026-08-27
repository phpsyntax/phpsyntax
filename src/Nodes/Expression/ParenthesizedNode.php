<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Node;
use PhpSyntax\Nodes\ArrayItemNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\MatchArmNode;
use PhpSyntax\Nodes\OperatorNode;
use PhpSyntax\Nodes\RightExtendingNode;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Token;
use PhpSyntax\TokenKind;


/**
 * Expression in parentheses.
 */
final class ParenthesizedNode extends ExpressionNode
{
	public const Slots = ['openParen', 'expression', 'closeParen'];

	/** an expression written without an operator, which no operator around it can take a part of */
	private const Tightest = PHP_INT_MAX;

	/** a place that takes whatever is written in it, having a delimiter of its own */
	private const Loosest = PHP_INT_MIN;

	/** the => between the key and the value of a pair, which the grammar declares among the operators */
	private const PairPrecedence = 70;


	/** @internal */
	public function __construct(
		public Token $openParen { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $expression { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $closeParen { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/**
	 * Whether the parentheses may go without the code coming to mean anything else: what stands in them
	 * binds at least as tightly as the place they stand in asks, and nothing reaches into them. Where the
	 * answer is not certain it is no.
	 */
	public function isRedundant(): bool
	{
		$access = $this->getAccessKind();
		if ($access !== null) {
			// what is reached into stays itself without them where it may be reached into bare; in ("str")() and
			// ($a + $b)->c they are what makes the expression one, and without them (FOO)::class would name the class
			// FOO, not the one the constant holds
			return $this->expression->isDereferenceable($access);
		} elseif (
			$this->parent instanceof UnaryOpNode
			&& str_starts_with($this->expression->text, $this->parent->operator->text)
		) {
			return false; // -(-$a) would read as a decrement
		}

		$asked = $this->askedFor();
		return $asked !== null && $this->bindsAs() >= $asked && !$this->wouldTakeWhatFollows();
	}


	/**
	 * How tightly what stands in the parentheses binds, seen from the left of where they stand: an operator
	 * with no left operand captures nothing before itself, however loosely it binds.
	 */
	private function bindsAs(): int
	{
		$expression = $this->expression;
		return match (true) {
			!$expression instanceof OperatorNode, self::hasNoLeftOperand($expression) => self::Tightest,
			default => $expression->getPrecedence()[0],
		};
	}


	/**
	 * Whether an operator ending what stands in the parentheses, the expression itself or the last operand
	 * down it, would take what follows them for its own: any operator at all where its operand reaches as far
	 * as the code lets it, otherwise one binding tighter than itself, and the => of a pair for a yield with none.
	 */
	private function wouldTakeWhatFollows(): bool
	{
		$followed = $this->findFollowedAncestor();
		$isPair = $followed instanceof ArrayItemNode || $followed instanceof MatchArmNode;
		if (!$followed instanceof OperatorNode && !$isPair) {
			return false;
		}

		$node = $this->expression;
		while ($node instanceof OperatorNode) {
			$takes = match (true) {
				$isPair => $node instanceof YieldNode && $node->doubleArrow === null,
				$node instanceof RightExtendingNode => true,
				default => $followed->getPrecedence()[0] > $node->getPrecedence()[0],
			};
			if ($takes) {
				return true;
			}

			$children = $node->getChildren();
			$node = end($children);
		}

		return false;
	}


	private static function hasNoLeftOperand(OperatorNode $operator): bool
	{
		return $operator instanceof RightExtendingNode
			|| $operator instanceof UnaryOpNode
			|| $operator instanceof CastNode
			|| $operator instanceof PrefixOpNode;
	}


	/** The nearest ancestor the parentheses do not end, so that what follows them is a token of its own; null where the code ends. */
	private function findFollowedAncestor(): ?Node
	{
		for ($node = $this; $node->parent !== null; $node = $node->parent) {
			$siblings = $node->parent->getChildren();
			if ($node !== end($siblings)) {
				return $node->parent;
			}
		}

		return null;
	}


	/**
	 * How tightly the expression standing here has to bind for the parentheses to be needless; null where
	 * the place is one this does not judge.
	 */
	private function askedFor(): ?int
	{
		$parent = $this->parent;
		return match (true) {
			// a class is named, not written as an expression, so only what may name one stands there bare
			$parent instanceof NewNode,
			$parent instanceof InstanceofNode && $parent->class === $this => $this->expression->canNameClass() ? self::Tightest : null,

			// a key is followed by a => the yield standing there would take for a key of its own
			$parent instanceof ArrayItemNode && $parent->key === $this,
			$parent instanceof SeparatedNodeList && $parent->parent instanceof MatchArmNode
				=> $this->expression instanceof YieldNode && $this->expression->doubleArrow === null ? null : self::Loosest,

			// the key and the value of a yield stand on the two sides of a => that is an operator
			$parent instanceof YieldNode && $parent->key === $this => self::PairPrecedence + 1,
			$parent instanceof YieldNode && $parent->doubleArrow !== null => self::PairPrecedence,

			// a pipe takes an arrow function in parentheses only
			$parent instanceof BinaryOpNode && $parent->operator->is(TokenKind::Pipe) && $this->expression instanceof ArrowFunctionNode => null,

			// what takes a variable and nothing else takes it without parentheses too
			$parent instanceof PrefixOpNode, $parent instanceof PostfixOpNode => self::Tightest,

			// an operator takes what binds tighter than itself, and on the side it leans to what binds alike
			$parent instanceof OperatorNode => self::sideOf($parent, $this === $parent->getChildren()[0]),

			// anywhere else a keyword, a bracket or a comma is what bounds the expression
			default => self::Loosest,
		};
	}


	/** What one side of an operator takes; its first child is the one standing on the left of it. */
	private static function sideOf(OperatorNode $parent, bool $isLeft): int
	{
		[$precedence, $associativity] = $parent->getPrecedence();
		return $associativity === ($isLeft ? OperatorNode::LeftAssociative : OperatorNode::RightAssociative)
			? $precedence
			: $precedence + 1;
	}
}
