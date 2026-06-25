<?php declare(strict_types=1);

/**
 * Lexer round-trip over the committed corpus and, when PHPSYNTAX_CORPUS points to a directory, over that tree.
 */

use PhpSyntax\Lexer\Lexer;
use PhpSyntax\ParseException;
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


/**
 * @param  list<string>  $files
 * @return list<string>  failures
 */
function roundTrip(array $files): array
{
	$lexer = new Lexer;
	$failures = [];
	foreach ($files as $file) {
		$code = file_get_contents($file);
		if ($code === false) {
			throw new RuntimeException("Cannot read $file");
		}

		try {
			$tokens = $lexer->tokenize($code);
		} catch (ParseException $e) {
			try {
				PhpToken::tokenize($code, TOKEN_PARSE);
				$failures[] = "$file: {$e->getMessage()} on line $e->sourceLine, but PHP accepts the file";
			} catch (ParseError) {
			}
			continue;
		}

		if (implode('', $tokens) !== $code) {
			$failures[] = "$file: output differs from input";
		}
	}

	return $failures;
}


test('committed corpus', function () {
	$files = findFiles(__DIR__ . '/../../corpus');
	Assert::true(count($files) > 40);
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
