<?php

/**
 * Copied from Latte grammar/rebuildParsers.php (https://latte.nette.org), itself a port of nikic/php-parser grammar/rebuildParsers.php.
 */

require __DIR__ . '/phpyLang.php';

$grammarFileToName = [
	__DIR__ . '/php.y' => 'TagParserData',
];

$tokensFile     = __DIR__ . '/tokens.y';
$tokensTemplate = __DIR__ . '/tokens.template';
$skeletonFile   = __DIR__ . '/parser.template';
$tmpGrammarFile = __DIR__ . '/tmp_parser.phpy';
$tmpResultFile  = __DIR__ . '/tmp_parser.php';
$resultDir = __DIR__ . '/../../src/Latte/Compiler';
$tokenClassFile = $resultDir . '/Token.php';

$kmyacc = getenv('KMYACC');
if (!$kmyacc) {
	// Use phpyacc from dev dependencies by default.
	$kmyacc = __DIR__ . '/../vendor/ircmaxell/php-yacc/bin/phpyacc';
}

$options = array_flip($argv);
$optionDebug = isset($options['--debug']);
$optionKeepTmpGrammar = isset($options['--keep-tmp-grammar']);

///////////////////
/// Main script ///
///////////////////

$tokens = file_get_contents($tokensFile);

foreach ($grammarFileToName as $grammarFile => $name) {
	echo "Building temporary $name grammar file.\n";

	$grammarCode = file_get_contents($grammarFile);
	$grammarCode = str_replace('%tokens', $tokens, $grammarCode);
	$grammarCode = preprocessGrammar($grammarCode);

	file_put_contents($tmpGrammarFile, $grammarCode);

	echo "Building $name parser.\n";
	checkConflicts(execCmd($kmyacc, ...($optionDebug ? ['-t', '-v'] : []), ...['-m', $skeletonFile, '-p', $name, $tmpGrammarFile]));

	$resultCode = file_get_contents($tmpResultFile);
	$resultCode = removeTrailingWhitespace($resultCode);
	$resultCode = optimize($resultCode);

	ensureDirExists($resultDir);
	file_put_contents("$resultDir/$name.php", $resultCode);
	unlink($tmpResultFile);

	echo "Checking token numbers.\n";
	execCmd($kmyacc, '-m', $tokensTemplate, $tmpGrammarFile);
	$code = file_get_contents($tmpResultFile);
	unlink($tmpResultFile);
	$code = preg_replace_callback('~T_(\w+)~', fn($m) => 'Php_' . str_replace('_', '', ucwords(strtolower($m[1]), '_')), $code);
	$code = strtr($code, [
		'Php_Lnumber' => 'Php_Integer',
		'Php_Dnumber' => 'Php_Float',
		'Php_Sl' => 'Php_ShiftLeft',
		'Php_Sr' => 'Php_ShiftRight',
		'Php_SlEqual' => 'Php_ShiftLeftEqual',
		'Php_SrEqual' => 'Php_ShiftRightEqual',
		'Php_Inc' => 'Php_Increment',
		'Php_Dec' => 'Php_Decrement',
		'Php_MulEqual' => 'Php_MultiplyEqual',
		'Php_DivEqual' => 'Php_DivideEqual',
		'Php_ModEqual' => 'Php_ModuloEqual',
		'Php_Pow' => 'Php_Power',
		'Php_PowEqual' => 'Php_PowerEqual',
		'Php_NumString' => 'Php_NumericString',
		'Php_StringVarname' => 'Php_StringVariableName',
		'Php_PaamayimNekudotayim' => 'Php_DoubleColon',
		'Php_NsSeparator' => 'Php_NamespaceSeparator',
		'Php_AmpersandFollowedByVarOrVararg' => 'Php_AmpersandFollowedByVariableOrVariadic',
		'Php_AmpersandNotFollowedByVarOrVararg' => 'Php_AmpersandNotFollowedByVariableOrVariadic',
	]);
	checkTokenNumbers($code, $tokenClassFile);

	if (!$optionKeepTmpGrammar) {
		unlink($tmpGrammarFile);
	}
}


////////////////////////////////
/// Utility helper functions ///
////////////////////////////////

function ensureDirExists($dir)
{
	if (!is_dir($dir)) {
		mkdir($dir, 0o777, true);
	}
}


function execCmd($script, ...$args)
{
	$cmd = implode(' ', array_map(escapeshellarg(...), [PHP_BINARY, '-d', 'error_reporting=' . (E_ALL & ~E_DEPRECATED), $script, ...$args]));
	exec($cmd . ' 2>&1', $output, $status);
	if ($output) {
		echo '> ', $cmd, "\n", implode("\n", $output), "\n";
	}

	if ($status !== 0) {
		fwrite(STDERR, "The command ended with status $status.\n");
		exit(1);
	}

	return implode("\n", $output);
}


/**
 * phpyacc writes about the conflicts only where their number differs from what %expect declares, and
 * says nothing of it in its status, so a grammar that gained or lost one would pass for sound.
 */
function checkConflicts($output)
{
	if (str_contains($output, 'shift/reduce') || str_contains($output, 'reduce/reduce')) {
		fwrite(STDERR, "The conflicts of the grammar are no longer the ones %expect declares; run the build with --debug and y.output tells where they are.\n");
		exit(1);
	}
}


/**
 * Token.php is handwritten: besides the tokens of the grammar it holds those of the Latte and HTML
 * lexers and the behaviour of the class, which no template can produce. Only the numbers of the PHP
 * tokens come from the grammar, and the lexer sends them where the generated TokenToSymbol expects
 * them, so a shift that is not carried over makes the parser read one token as another. The build
 * therefore compares the two instead of overwriting the file.
 */
function checkTokenNumbers($generated, $file)
{
	$expected = tokenNumbers($generated);
	$actual = tokenNumbers(file_get_contents($file));
	$errors = [];
	foreach ($expected as $name => $number) {
		if (!isset($actual[$name])) {
			$errors[] = "$name = $number is missing";
		} elseif ($actual[$name] !== $number) {
			$errors[] = "$name is $actual[$name], the grammar numbers it $number";
		}
	}

	foreach (array_diff_key($actual, $expected) as $name => $number) {
		$errors[] = "$name = $number is no token of the grammar";
	}

	if ($errors) {
		echo "\nThe Php_* constants of Token.php no longer match the grammar:\n  ";
		echo implode("\n  ", $errors);
		echo "\nThe file is handwritten, so carry the numbers over by hand.\n";
		exit(1);
	}

	echo count($expected) . " token numbers match.\n";
}


/** The Php_* constants of a token class and the numbers they are declared with. */
function tokenNumbers($code)
{
	preg_match_all('~^\s*(Php_\w+) = (\d+)~m', $code, $matches, PREG_SET_ORDER);
	$numbers = [];
	foreach ($matches as [, $name, $number]) {
		$numbers[$name] = (int) $number;
	}

	return $numbers;
}
