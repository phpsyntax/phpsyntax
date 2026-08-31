<?php declare(strict_types=1);

/**
 * Adding, replacing and removing the types of a signature: a return type with its colon, the type of
 * a parameter and of a property with the space after it, while the comments and the brace stay put.
 *
 * Demonstrates: `FunctionLikeNode::setReturnType()`, `ParameterNode::setType()`, `PropertyNode::setType()`,
 *               `Builder::type()`
 * Usage:        php examples/mutation/signatures.php
 */

require __DIR__ . '/../bootstrap.php';

use PhpSyntax\Builder;
use PhpSyntax\Nodes\Member\{MethodNode, PropertyNode};
use PhpSyntax\Parser;

$code = sample(<<<'PHP'
	<?php
	class Cart
	{
		public $items = [];
		private /* cached */ $total;

		public function __construct(array $items = []) : void
		{
			$this->items = $items;
		}

		public function add($item, mixed $note = null)
		{
			$this->items[] = $item;
		}

		public function getTotal(): float   // without VAT
		{
			return $this->total ??= array_sum($this->items);
		}
	}
	PHP);

$file = new Parser()->parse($code);
$builder = new Builder;
$method = fn(string $name) => $file->findFirst(MethodNode::class, fn(MethodNode $node) => $node->name->equals($name));
[$items, $total] = $file->find(PropertyNode::class);

// a constructor declares no return type, so the fix removes it, colon and all
$method('__construct')->setReturnType(null);

// a type added where there was none, and one taken away: `mixed` on a parameter says nothing
$add = $method('add');
$add->setReturnType($builder->type('void'));
$add->parameters[0]->setType($builder->type('Item'));
$add->parameters[1]->setType(null);

// a property typed by a type that already stands in the file: the setter takes a copy
$items->setType($builder->type('array'));
$total->setType($method('getTotal')->returnType);

printDiff($code, (string) $file);
