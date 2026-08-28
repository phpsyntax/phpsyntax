<?php declare(strict_types=1);

namespace PhpSyntax\Nodes\Expression;

use PhpSyntax\Lexer\Lexer;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\OperatorNode;
use PhpSyntax\Token;
use PhpSyntax\Trivia;
use PhpSyntax\TriviaKind;


/**
 * Binary operation; the operator token tells which (arithmetic, comparison, logical, bitwise, concatenation, coalesce, pipe).
 */
final class BinaryOpNode extends ExpressionNode implements OperatorNode
{
	public const Slots = ['left', 'operator', 'right'];


	/** @internal */
	public function __construct(
		public ExpressionNode $left { set => $this->prepareSlot(__PROPERTY__, $value); },
		public Token $operator { set => $this->prepareSlot(__PROPERTY__, $value); },
		public ExpressionNode $right { set => $this->prepareSlot(__PROPERTY__, $value); },
	) {
	}


	/**
	 * The operation on the two operands, each in parentheses where it binds looser than its side of the operator takes.
	 * @throws \InvalidArgumentException  for what is no binary operator
	 */
	public static function of(ExpressionNode $left, string $operator, ExpressionNode $right): self
	{
		static $lexer = new Lexer;
		$space = [new Trivia(TriviaKind::Whitespace, ' ')];
		$token = new Token($lexer->tokenize('<?php ' . $operator, withPositions: false)[0]->kind, $operator);
		$token->setTrailingTrivia($space);
		$node = new self(ParenthesizedNode::of($left), $token, ParenthesizedNode::of($right));
		try {
			$node->getPrecedence();
		} catch (\LogicException $e) {
			throw new \InvalidArgumentException($e->getMessage());
		}

		foreach ([$node->left, $node->right] as $operand) {
			if ($operand instanceof ParenthesizedNode && $operand->isRedundant()) {
				$operand->replaceWith($operand->expression);
			}
		}

		$node->left->setEdgeTrivia(trailing: $space);
		return $node;
	}


	public function getPrecedence(): array
	{
		return match (strtolower($this->operator->text)) {
			'**' => [250, self::RightAssociative],
			'*', '/', '%' => [210, self::LeftAssociative],
			'+', '-' => [200, self::LeftAssociative],
			'<<', '>>' => [190, self::LeftAssociative],
			'.' => [185, self::LeftAssociative],
			'|>' => [183, self::LeftAssociative],
			'<', '<=', '>', '>=' => [180, self::NonAssociative],
			'==', '!=', '<>', '===', '!==', '<=>' => [170, self::NonAssociative],
			'&' => [160, self::LeftAssociative],
			'^' => [150, self::LeftAssociative],
			'|' => [140, self::LeftAssociative],
			'&&' => [130, self::LeftAssociative],
			'||' => [120, self::LeftAssociative],
			'??' => [110, self::RightAssociative],
			'and' => [50, self::LeftAssociative],
			'xor' => [40, self::LeftAssociative],
			'or' => [30, self::LeftAssociative],
			default => throw new \LogicException("'{$this->operator->text}' is no binary operator."),
		};
	}
}
