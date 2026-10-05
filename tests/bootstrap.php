<?php declare(strict_types=1);

if (@!include __DIR__ . '/../vendor/autoload.php') { // @ dependencies may not be installed
	echo 'Install Nette Tester using `composer install`';
	exit(1);
}

require __DIR__ . '/Dumper.php';
require __DIR__ . '/Snapshot.php';

Tester\Environment::setup();
Tester\Environment::setupFunctions();


/**
 * Returns the expected output stored after __halt_compiler() in a test file.
 */
function loadExpected(string $file, int $offset): string
{
	return ltrim((string) file_get_contents($file, offset: $offset), "\r\n");
}


/**
 * Returns the PHP a test runs code with: the binary of the process, or under the CGI the CLI binary beside it,
 * the test skipped without one.
 */
function getPhpCliBinary(): string
{
	if (PHP_SAPI === 'cli') {
		return PHP_BINARY;
	}

	$path = dirname(PHP_BINARY) . '/php' . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
	if (!is_file($path)) {
		Tester\Environment::skip('No CLI binary of PHP beside ' . PHP_BINARY . '.');
	}

	return $path;
}
