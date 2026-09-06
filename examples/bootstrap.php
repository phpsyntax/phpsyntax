<?php declare(strict_types=1);

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo "Install dependencies using `composer install`\n";
	exit(1);
}


/** The name of a node class without its namespace, for a listing that would otherwise not fit. */
function shortClass(object $node): string
{
	return substr(strrchr($node::class, '\\') ?: '', 1);
}


/**
 * The value, or an error: an example knows its own sample code, so a null here means the example
 * is broken. Your own tool asks whether the slot is filled instead.
 * @template T of object
 * @param  ?T  $value
 * @return T
 */
function must(?object $value): object
{
	return $value ?? throw new LogicException('The example expected a node or a token here.');
}


/**
 * The sample code of an example with its line endings normalized to "\n", so that the output is
 * the same whatever the checkout does with the line endings of this file.
 */
function sample(string $code): string
{
	return str_replace("\r\n", "\n", $code);
}


/**
 * Prints the lines that differ between two texts, the way a reviewer would see them in a diff:
 * "-" for a line that went away, "+" for one that came, everything else left out.
 */
function printDiff(string $old, string $new): void
{
	$a = explode("\n", $old);
	$b = explode("\n", $new);
	$lengths = [];
	for ($i = count($a); $i >= 0; $i--) {
		for ($j = count($b); $j >= 0; $j--) {
			$lengths[$i][$j] = match (true) {
				$i === count($a) || $j === count($b) => 0,
				$a[$i] === $b[$j] => $lengths[$i + 1][$j + 1] + 1,
				default => max($lengths[$i + 1][$j], $lengths[$i][$j + 1]),
			};
		}
	}

	$i = $j = 0;
	while ($i < count($a) || $j < count($b)) {
		if ($i < count($a) && $j < count($b) && $a[$i] === $b[$j]) {
			$i++;
			$j++;
		} elseif ($i < count($a) && ($j === count($b) || $lengths[$i + 1][$j] >= $lengths[$i][$j + 1])) {
			echo '- ', $a[$i++], "\n";
		} else {
			echo '+ ', $b[$j++], "\n";
		}
	}
}
