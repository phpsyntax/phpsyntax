<?php declare(strict_types=1);

/**
 * Verifies that the output quoted in the readme of every example directory still matches what the
 * script prints: under each heading naming a script in parentheses, the first fenced block without
 * a language is compared with a real run, chunk by chunk, where "..." stands for anything left out.
 * Run it before committing: composer verify-examples
 */

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo "Install dependencies using `composer install`\n";
	exit(1);
}

$failures = 0;

// the PHP shown in the project readme is the code most people copy, so at least it has to parse
$readmeText = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../readme.md'));
preg_match_all('~^```php\n(.*?)^```~ms', $readmeText, $blocks, PREG_SET_ORDER);
foreach ($blocks as $i => [, $block]) {
	$temp = tempnam(sys_get_temp_dir(), 'psreadme');
	file_put_contents($temp, "<?php\n" . $block);
	exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($temp) . ' 2>&1', $lines, $status);
	unlink($temp);
	if ($status === 0) {
		echo 'readme.md: PHP block #', $i + 1, " parses\n";
	} else {
		echo 'readme.md: PHP block #', $i + 1, " does not parse:\n", implode("\n", $lines), "\n";
		$failures++;
	}

	$lines = [];
}

foreach (glob(__DIR__ . '/*/readme.md') ?: [] as $readme) {
	$dir = dirname($readme);
	$text = str_replace("\r\n", "\n", (string) file_get_contents($readme));
	// a heading names its script in parentheses: "## What it shows (script.php)"
	preg_match_all('~^##[^\n]*\((\S+\.php)\)[^\n]*$(.*?)(?=^## |\z)~ms', $text, $sections, PREG_SET_ORDER);
	$described = array_column($sections, 1);
	foreach (glob("$dir/*.php") ?: [] as $script) {
		if (!in_array(basename($script), $described, strict: true)) {
			echo basename($dir), '/', basename($script), ": the readme has no heading naming this script\n";
			$failures++;
		}
	}

	foreach ($sections as [, $script, $body]) {
		$name = basename($dir) . '/' . $script;
		if (!is_file("$dir/$script")) {
			echo "$name: the readme describes a script that does not exist\n";
			$failures++;
			continue;
		}

		$quoted = findOutputBlock($body);
		if ($quoted === null) {
			continue; // a script whose output the readme does not quote
		}

		exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$dir/$script") . ' 2>&1', $lines, $code);
		$actual = implode("\n", $lines);
		$lines = [];
		if ($code !== 0) {
			echo "$name: exited with code $code\n$actual\n";
			$failures++;
			continue;
		}

		$missing = findMissingChunk($quoted, $actual);
		if ($missing === null) {
			echo "$name: OK\n";
		} else {
			echo "$name: the readme quotes output the script does not print:\n" . preg_replace('~^~m', '    ', $missing) . "\n";
			$failures++;
		}
	}
}

exit($failures === 0 ? 0 : 1);


/** The content of the first fenced block that carries no language, which is how output is quoted. */
function findOutputBlock(string $body): ?string
{
	preg_match_all('~^```(\w*)\n(.*?)^```~ms', $body, $blocks, PREG_SET_ORDER);
	foreach ($blocks as [, $language, $content]) {
		if ($language === '' || $language === 'text') {
			return $content;
		}
	}

	return null;
}


/** The first chunk between "..." markers that the output does not contain in order; null when all are there. */
function findMissingChunk(string $quoted, string $actual): ?string
{
	$offset = 0;
	foreach (preg_split('~^\s*\.\.\.\s*$~m', trim($quoted)) ?: [] as $chunk) {
		$chunk = trim($chunk, "\n");
		if ($chunk === '') {
			continue;
		}

		$pos = strpos($actual, $chunk, $offset);
		if ($pos === false) {
			return $chunk;
		}

		$offset = $pos + strlen($chunk);
	}

	return null;
}
