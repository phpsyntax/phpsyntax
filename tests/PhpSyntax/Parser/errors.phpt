<?php declare(strict_types=1);

/**
 * Inputs the parser must reject; every one of them is rejected by php -l as well.
 */

use PhpSyntax\ParseException;
use PhpSyntax\Parser;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';


$inputs = [
	'<?php $a = ;',
	'<?php function f( { }',
	'<?php class A { public function }',
	'<?php if ($a) { echo 1; ',
	'<?php $a = [1, 2',
	'<?php foreach ($a as) {}',
	'<?php $a->;',
	'<?php $a = "unterminated',
	'<?php echo 1 ?> <?php }',
	'<?php match ($a) { 1 => }',
	'<?php fn($x) => ;',
	'<?php enum E: { }',
];

$temp = tempnam(sys_get_temp_dir(), 'dc');
foreach ($inputs as $code) {
	try {
		(new Parser)->parse($code);
		Assert::fail("parser accepts: $code");
	} catch (ParseException) {
	}

	file_put_contents($temp, $code);
	exec(escapeshellarg(PHP_BINARY) . ' -n -l ' . escapeshellarg($temp) . ' 2>&1', $output, $exitCode);
	Assert::notSame(0, $exitCode, "php -l accepts: $code");
}

unlink($temp);
