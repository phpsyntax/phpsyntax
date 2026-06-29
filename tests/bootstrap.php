<?php declare(strict_types=1);

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer install`';
	exit(1);
}

require __DIR__ . '/Dumper.php';

Tester\Environment::setup();
Tester\Environment::setupFunctions();


/**
 * Returns the expected output stored after __halt_compiler() in a test file.
 */
function loadExpected(string $file, int $offset): string
{
	return ltrim((string) file_get_contents($file, offset: $offset), "\r\n");
}
