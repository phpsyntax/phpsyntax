<?php declare(strict_types=1);

use PhpSyntax\{Node, Token};
use PhpSyntax\Nodes\FileNode;
use Tester\Assert;


/**
 * Everything a refused write must leave as it was: the tokens of the trees in their order with their texts, parents
 * and the very trivia they carry, the nodes with their parents, the printed text and the revision and the index of
 * a file.
 */
final class Snapshot
{
	/** @var list<array<string, mixed>> */
	private array $states;


	private function __construct(
		/** @var list<(Node | Token)> */
		private readonly array $roots,
	) {
		$this->states = array_map(self::describe(...), $roots);
	}


	public static function take(Node|Token ...$roots): self
	{
		return new self(array_values($roots));
	}


	public function assertUnchanged(string $description = ''): void
	{
		foreach ($this->roots as $i => $root) {
			Assert::same($this->states[$i], self::describe($root), $description);
		}
	}


	/** @return array<string, mixed> */
	private static function describe(Node|Token $root): array
	{
		$tokens = $nodes = [];
		$stack = [$root];
		while ($stack) {
			$node = array_pop($stack);
			if ($node instanceof Token) {
				$tokens[] = [
					spl_object_id($node),
					$node->id,
					$node->text,
					$node->parent === null ? null : spl_object_id($node->parent),
					array_map(fn($trivia) => [spl_object_id($trivia), $trivia->text], $node->leadingTrivia),
					array_map(fn($trivia) => [spl_object_id($trivia), $trivia->text], $node->trailingTrivia),
				];
				continue;
			}

			$nodes[] = [$node::class, spl_object_id($node), $node->parent === null ? null : spl_object_id($node->parent)];
			$children = $node->getChildren();
			for ($i = count($children) - 1; $i >= 0; $i--) {
				$stack[] = $children[$i];
			}
		}

		$state = ['print' => (string) $root, 'tokens' => $tokens, 'nodes' => $nodes];
		if ($root instanceof FileNode) {
			$state['revision'] = $root->revision;
			$state['index'] = array_map(spl_object_id(...), $root->getTokens()); // the order the index keeps agrees with the tree
		}

		return $state;
	}
}


/**
 * Asserts that the operation throws the exception and leaves every tree given as it stood.
 * @param  callable(): mixed  $operation
 * @param  class-string<Throwable>  $exception
 */
function assertRefused(callable $operation, string $exception, ?string $message = null, Node|Token ...$roots): void
{
	$snapshot = Snapshot::take(...$roots);
	Assert::exception($operation, $exception, $message);
	$snapshot->assertUnchanged('the refused operation changed a tree');
}
