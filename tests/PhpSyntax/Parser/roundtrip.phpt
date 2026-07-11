<?php declare(strict_types=1);

/**
 * Parser round-trip over the committed corpus and, when PHPSYNTAX_CORPUS points to a directory, over that tree.
 * A file the parser rejects is a failure unless php -l rejects it too.
 */

use PhpSyntax\ParseException;
use PhpSyntax\Parser;
use PhpSyntax\Printer;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


/** @return list<string> */
function findFiles(string $dir): array
{
	$files = [];
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file) {
		if (preg_match('~\.(php|phpt|inc)$~', $file->getFilename())) {
			$files[] = $file->getPathname();
		}
	}

	sort($files);
	return $files;
}


function isRejectedByPhp(string $file): bool
{
	exec(escapeshellarg(PHP_BINARY) . ' -n -l ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);
	return $exitCode !== 0;
}


/**
 * @param  list<string>  $files
 * @return list<string>  failures
 */
function roundTrip(array $files): array
{
	$parser = new Parser;
	$failures = [];
	foreach ($files as $file) {
		$code = file_get_contents($file);
		if ($code === false) {
			throw new RuntimeException("Cannot read $file");
		}

		try {
			$node = $parser->parse($code);
		} catch (ParseException $e) {
			if (!isRejectedByPhp($file)) {
				$failures[] = "$file: {$e->getMessage()} on line $e->sourceLine, but PHP accepts the file";
			}
			continue;
		}

		if (Printer::print($node) !== $code) {
			$failures[] = "$file: output differs from input";
		}
	}

	return $failures;
}


test('committed corpus', function () {
	$files = findFiles(__DIR__ . '/../../corpus');
	Assert::true(count($files) > 100);
	Assert::same([], roundTrip($files));
});


$external = getenv('PHPSYNTAX_CORPUS');
if ($external !== false) {
	test('external corpus', function () use ($external) {
		Assert::true(is_dir($external), "PHPSYNTAX_CORPUS '$external' is not a directory");
		$files = findFiles($external);
		echo count($files), " files\n";
		Assert::same([], roundTrip($files));
	});
}
