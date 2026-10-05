<?php declare(strict_types=1);

/**
 * Random mutations of real files interleaved with position queries, each answer checked against an index built
 * afresh over the printed text. Deterministic: the seed comes from the name of the file.
 */

use PhpSyntax\{Builder, Parser, Printer, Style, Token, Trivia};
use PhpSyntax\Nodes\{ArrayItemNode, FileNode, IdentifierNode, PlainNodeList};
use PhpSyntax\Nodes\Expression\{ArrayNode, MethodCallNode};
use PhpSyntax\Nodes\Scalar\{HeredocNode, InterpolatedStringNode};
use PhpSyntax\Nodes\Statement\{ExpressionStatementNode, InlineHtmlNode};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


/** Whether whitespace next to the token would be text rather than trivia, which a mutation must not touch. */
function standsInText(Token $token): bool
{
	return $token->parent instanceof InlineHtmlNode
		|| $token->findAncestor(InterpolatedStringNode::class) !== null
		|| $token->findAncestor(HeredocNode::class) !== null
		|| $token->is(Token::CloseTag)
		|| preg_match('~[\r\n]$~', $token->text) === 1;
}


/** Returns whether the mutation was one the tree refuses. */
function mutate(FileNode $file, Parser $parser): bool
{
	$builder = new Builder($parser);
	$tokens = $file->getIndex()->getTokens();
	$token = $tokens[mt_rand(0, count($tokens) - 2)]; // never the end of file
	$kind = standsInText($token) ? 0 : mt_rand(0, 9);
	if ($kind === 0 && $token->parent instanceof IdentifierNode) {
		$token->parent->text = $token->text . 'é';

	} elseif ($kind === 1) {
		$token->setLeadingTrivia([...$token->leadingTrivia, new Trivia(Trivia::Whitespace, ' ')]);

	} elseif ($kind === 2) {
		$token->setTrailingTrivia([new Trivia(Trivia::Whitespace, "\t"), new Trivia(Trivia::Comment, '/* x */')]);

	} elseif ($kind === 3 && ($token->trailingTrivia[0] ?? null)?->is(Trivia::LineEnding)) {
		$token->setTrailingTrivia([...$token->trailingTrivia, new Trivia(Trivia::LineEnding, "\r\n")]);

	} elseif ($kind === 4 || $kind === 5) {
		$statements = $file->find(ExpressionStatementNode::class, fn($node) => $node->parent instanceof PlainNodeList);
		$statement = $statements ? $statements[mt_rand(0, count($statements) - 1)] : null;
		if ($statement?->parent instanceof PlainNodeList && $kind === 4 && count($statement->parent) > 1) {
			$statement->remove();
		} elseif (
			$statement?->parent instanceof PlainNodeList
			&& $kind === 5
			&& !standsInText($statement->getFirstToken()->getPrevious() ?? $token)
		) {
			// not right after a close tag or inline HTML, where the statement would be read as text
			$statement->parent->insert($statement->parent->indexOf($statement), $builder->statement('$inserted = 1;'));
		}

	} elseif ($kind === 6) {
		$names = $file->find(MethodCallNode::class, fn($call) => $call->name instanceof IdentifierNode);
		$name = $names ? $names[mt_rand(0, count($names) - 1)]->name : null;
		if ($name instanceof IdentifierNode) {
			$name->replaceWith(IdentifierNode::fromText($name->text . (mt_rand(0, 1) ? 'Y' : 'Longer')));
		}

	} elseif ($kind === 7) {
		$statements = $file->find(ExpressionStatementNode::class);
		$statement = $statements ? $statements[mt_rand(0, count($statements) - 1)] : null;
		$statement?->expression->replaceWith($builder->expression(['$z', 'f(1, 2)', '$a->b[0]'][mt_rand(0, 2)]));

	} elseif ($kind === 8) {
		// an item taken out and put back at the end, then a trailing separator, all before the next query
		$arrays = $file->find(ArrayNode::class, fn($array) => count($array->items) > 1 && !$array->items->hasTrailingSeparator());
		$list = $arrays ? $arrays[mt_rand(0, count($arrays) - 1)]->items : null;
		$item = $list ? $list[mt_rand(0, count($list) - 1)] : null;
		if ($list && $item instanceof ArrayItemNode) { // not an empty item of a destructuring
			$list->removeItem($item);
			$list->append($item, new Token(ord(','), ','));
			$list->setTrailingSeparator(new Token(ord(','), ','));
		}

	} elseif ($kind === 9) {
		return refuse($file);
	}

	return false;
}


/**
 * A write the tree refuses, which must leave the file as it stood: a node standing elsewhere in it, a cycle, a text
 * of another kind. Returns whether one was tried.
 */
function refuse(FileNode $file): bool
{
	$statements = $file->find(ExpressionStatementNode::class);
	if (count($statements) < 2) {
		return false;
	}

	$first = $statements[mt_rand(0, count($statements) - 1)];
	$second = $statements[mt_rand(0, count($statements) - 1)];
	$inner = $first->expression->findFirst(PhpSyntax\Nodes\ExpressionNode::class);
	[$operation, $exception] = match (mt_rand(0, 2)) {
		0 => [$first === $second ? null : fn() => $first->expression = $second->expression, LogicException::class],
		1 => [$inner === null ? null : fn() => $inner->replaceWith($first->expression), LogicException::class],
		2 => [fn() => $first->semicolon->setText('x'), InvalidArgumentException::class],
	};
	if ($operation === null) {
		return false;
	}

	assertRefused($operation, $exception, null, $file);
	return true;
}


/** @return array{string, int, int, int, int} */
function describe(Token $token, Style $style): array
{
	return [
		$token->text,
		(int) $token->getCurrentLine(),
		(int) $token->getCurrentColumn(),
		(int) $token->getCurrentOffset(),
		(int) $token->getVisualColumn($style),
	];
}


$files = glob(__DIR__ . '/../corpus/wild/*.php') ?: [];
Assert::true(count($files) > 30);
$parser = new Parser;
$builder = new Builder;
$style = new Style;
$refusals = 0;
foreach (array_filter($files, fn($i) => $i % 6 === 0, ARRAY_FILTER_USE_KEY) as $path) {
	mt_srand(crc32(basename($path)));
	$file = $parser->parse((string) file_get_contents($path));
	$index = $file->getIndex();
	for ($step = 1; $step <= 120; $step++) {
		for ($writes = mt_rand(1, 4); $writes > 0; $writes--) { // several writes before a query, as a batch of a rule
			$refusals += (int) mutate($file, $parser);
		}

		$tokens = $index->getTokens();
		$query = $tokens[mt_rand(0, count($tokens) - 1)];
		$answer = describe($query, $style);
		if ($step % 30 === 0) {
			$fresh = $parser->parse(Printer::print($file))->getIndex()->getTokens();
			Assert::same($fresh[$index->getOrdinal($query)]->text, $query->text, basename($path) . " step $step");
			Assert::same(describe($fresh[$index->getOrdinal($query)], $style), $answer, basename($path) . " step $step");
			Assert::same(
				array_map(fn(Token $token) => describe($token, $style), $fresh),
				array_map(fn(Token $token) => describe($token, $style), $index->getTokens()),
				basename($path) . " step $step",
			);
		}
	}
}

Assert::true($refusals > 50, "only $refusals refused writes were tried"); // the refusals are a category of the fuzz, not a chance
