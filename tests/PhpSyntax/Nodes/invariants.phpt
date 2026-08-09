<?php declare(strict_types=1);

/**
 * A fixed sequence of mutations over real code keeps the parent invariant and prints what the mutations imply,
 * and a generated one keeps the tree, the index and the printed code in agreement.
 */

use PhpSyntax\CommentPolicy;
use PhpSyntax\Node;
use PhpSyntax\Nodes\ArgumentNode;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\FileNode;
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use PhpSyntax\Parser;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


function assertParents(Node $node): void
{
	foreach ($node->getChildren() as $child) {
		Assert::same($node, $child->parent);
		if ($child instanceof Node) {
			assertParents($child);
		}
	}
}


function assertIndexed(FileNode $file): void
{
	$printed = (string) $file;
	foreach ($file->getIndex()->getTokens() as $token) {
		Assert::same($token->text, substr($printed, (int) $token->getOffset(), strlen($token->text)));
	}
}


/**
 * Everything the tree promises about itself: one owner for every child, nothing standing in it twice
 * (which is also what a cycle would look like), and an index holding the same tokens in the same order.
 */
function assertIntegrity(FileNode $file): void
{
	$seen = [];
	$stack = [$file];
	while ($stack) {
		$node = array_pop($stack);
		Assert::false(isset($seen[spl_object_id($node)]), 'the node stands in the tree twice');
		$seen[spl_object_id($node)] = true;
		foreach ($node instanceof Node ? $node->getChildren() : [] as $child) {
			Assert::same($node, $child->parent);
			$stack[] = $child;
		}
	}

	Assert::same($file->getTokens(), $file->getIndex()->getTokens());
	assertIndexed($file);
}


test('mutations over a real file', function () {
	$code = (string) file_get_contents(__DIR__ . '/../../corpus/wild/nette-utils.Arrays.php');
	$file = (new Parser)->parse($code);
	$parser = new Parser;
	$mutations = 0;

	foreach ($file->find(ExpressionStatementNode::class) as $i => $stmt) {
		if (!$stmt->getFile()) { // inside a subtree removed earlier
			continue;
		}

		match ($i % 4) {
			0 => $stmt->remove(),
			1 => $stmt->remove(CommentPolicy::Drop),
			2 => $stmt->replaceWith($parser->parseStatement('replaced();')),
			3 => $stmt->expression->replaceWith(clone $stmt->expression),
		};
		$mutations++;
	}

	Assert::true($mutations > 20);
	Assert::true($file->revision >= $mutations); // a compound mutation moves trivia in several steps
	assertParents($file);
	assertIndexed($file);
	Assert::true(str_contains((string) $file, 'replaced();'));
	Assert::false(str_contains((string) $file, "\n\n\n\n"));

	$copy = clone $file;
	assertParents($copy);
	Assert::same((string) $file, (string) $copy);
});


/**
 * One of the nodes that a step earlier has not taken out of the tree.
 * @template T of Node
 * @param  list<T>  $nodes
 * @return ?T
 */
function pickAlive(array $nodes): ?Node
{
	$alive = array_values(array_filter($nodes, fn(Node $node) => $node->getFile() !== null));
	return $alive === [] ? null : $alive[mt_rand(0, count($alive) - 1)];
}


test('a generated sequence of mutations leaves the tree and the index in agreement', function () {
	$code = (string) file_get_contents(__DIR__ . '/../../corpus/wild/nette-utils.Arrays.php');
	$parser = new Parser;

	foreach ([1, 2, 3, 4] as $seed) {
		mt_srand($seed);
		$file = $parser->parse($code);
		$refused = 0;
		for ($step = 0; $step < 60; $step++) {
			switch (mt_rand(0, 6)) {
				case 0:
					pickAlive($file->find(ExpressionStatementNode::class))?->remove(mt_rand(0, 1) ? CommentPolicy::Drop : CommentPolicy::MoveToNextToken);
					break;
				case 1:
					$file->statements->append($parser->parseStatement('appended();'));
					break;
				case 2:
					pickAlive($file->find(VariableNode::class))?->replaceWith($parser->parseExpression('$renamed'));
					break;
				case 3:
					pickAlive($file->find(FunctionCallNode::class))?->arguments->items->append($parser->parseFragment(ArgumentNode::class, '$extra'));
					break;
				case 4:
					$argument = pickAlive($file->find(ArgumentNode::class));
					$argument?->remove();
					break;
				case 5:
					$token = pickAlive($file->find(ExpressionStatementNode::class))?->getFirstToken();
					if ($token?->startsLine()) {
						$token->setBlankLinesBefore(mt_rand(0, 2));
					}

					break;
				case 6:
					$statement = pickAlive($file->find(ExpressionStatementNode::class));
					$call = pickAlive($file->find(FunctionCallNode::class));
					$before = (string) $file;
					if (mt_rand(0, 1) === 1 && $statement !== null) {
						// a statement is no expression, and a write the slot refuses may move nothing of the tree
						Assert::exception(
							fn() => $statement->expression->replaceWith($parser->parseStatement('return;')),
							InvalidArgumentException::class,
						);
						$refused++;
					} elseif ($call !== null) {
						// a token standing in the tree refuses an insertion of two values, and the item stays where it came from
						$fragment = $parser->parseExpression('g($extra)');
						Assert::type(FunctionCallNode::class, $fragment);
						$item = $fragment->arguments->items->getItems()[0];
						Assert::exception(
							fn() => $call->arguments->items->append($item, $call->arguments->openParen),
							LogicException::class,
						);
						Assert::same($fragment->arguments->items, $item->parent);
						$refused++;
					}

					Assert::same($before, (string) $file);
					break;
			}

			$file->getIndex()->getTokens(); // the index is asked after every step, so a wrong one is found at once
		}

		Assert::true($refused > 0);
		assertIntegrity($file);
		Assert::same((string) $file, (string) $parser->parse((string) $file)); // and it is still code that parses
	}
});
