<?php declare(strict_types=1);

/**
 * Ported from Latte grammar/rebuildParsers.php (https://latte.nette.org), itself a port of nikic/php-parser grammar/rebuildParsers.php.
 *
 * Generates src/ParserData.php and src/TokenData.php from grammar/php.y, and through nodes-generator.php
 * src/LayoutData.php and the Slots constant, the slot properties and the constructor of each node class not marked
 * manual from grammar/nodes.php.
 * Options: --debug (keeps y.output and the preprocessed grammar), --strip-actions (removes all actions from php.y).
 */

require __DIR__ . '/phpyLang.php';

chdir(__DIR__); // phpyacc writes y.output to the working directory
$grammarFile = __DIR__ . '/php.y';
$phpyacc = __DIR__ . '/vendor/bin/phpyacc';
$tmpGrammarFile = __DIR__ . '/tmp_parser.phpy';
$tmpResultFile = __DIR__ . '/tmp_parser.php';
$srcDir = __DIR__ . '/../src';

$options = array_flip(array_slice($argv, 1));
$optionDebug = isset($options['--debug']);

if (isset($options['--strip-actions'])) {
	$grammar = file_get_contents($grammarFile);
	$grammar = stripActions($grammar);
	file_put_contents($grammarFile, $grammar);
	echo "Actions removed from php.y\n";
	exit;
}


///////////////////
/// Main script ///
///////////////////

// a checkout on Windows may hand the file over with CRLF, which the patterns below do not expect
$grammar = str_replace("\r\n", "\n", file_get_contents($grammarFile));
$grammar = preprocessGrammar($grammar);
file_put_contents($tmpGrammarFile, $grammar);

echo "Building token kinds.\n";
execCmd($phpyacc, '-m', __DIR__ . '/tokens.template', $tmpGrammarFile);
$tokens = readTokenNumbers(file_get_contents($tmpResultFile));
file_put_contents("$srcDir/TokenData.php", buildTokenData($tokens));
unlink($tmpResultFile);

echo "Building parser.\n";
checkConflicts(execCmd($phpyacc, ...($optionDebug ? ['-t', '-v'] : []), ...['-m', __DIR__ . '/parser.template', '-p', 'ParserData', $tmpGrammarFile]));
$code = file_get_contents($tmpResultFile);
$code = keyTokenTable($code, $tokens);
$code = removeTrailingWhitespace($code);
$code = optimize($code);
file_put_contents("$srcDir/ParserData.php", $code);
unlink($tmpResultFile);

echo "Building node classes.\n";
require __DIR__ . '/nodes-generator.php';
buildNodes(require __DIR__ . '/nodes.php', "$srcDir/Nodes");

echo "Building layout data.\n";
file_put_contents("$srcDir/LayoutData.php", renderLayoutData(require __DIR__ . '/nodes.php'));

if (!$optionDebug) {
	unlink($tmpGrammarFile);
}


////////////////////////////////
/// Utility helper functions ///
////////////////////////////////

/**
 * Runs a PHP script and ends the build when it fails, so that nothing is generated from what it did not
 * write; every argument is escaped, a path with a space in it among them.
 */
function execCmd(string $script, string ...$args): string
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
function checkConflicts(string $output): void
{
	if (str_contains($output, 'shift/reduce') || str_contains($output, 'reduce/reduce')) {
		fwrite(STDERR, "The conflicts of the grammar are no longer the ones %expect declares; run the build with --debug and y.output tells where they are.\n");
		exit(1);
	}
}


/**
 * Removes all semantic actions ({ ... } blocks) from the grammar, keeping quoted tokens like '{' intact.
 */
function stripActions(string $grammar): string
{
	$grammar = preg_replace(regex('(?&string)(*SKIP)(*FAIL)|(?&code)'), '', $grammar);
	$grammar = removeTrailingWhitespace($grammar);
	return preg_replace('~\n\n+(?=[ \t]*[|;])~', "\n", $grammar);
}


/**
 * The tokens of the grammar, T_* names to the numbers phpyacc gave them, from the list tokens.template produces.
 * @return array<string, int>
 */
function readTokenNumbers(string $list): array
{
	preg_match_all('~^(T_\w+) = (\d+)$~m', $list, $matches, PREG_SET_ORDER);
	return array_combine(array_column($matches, 1), array_map(intval(...), array_column($matches, 2)));
}


/** The name of the kind of a T_* token: CamelCase, with the renames that make it read as a word. */
function nameKind(string $token): string
{
	$renames = [
		'Lnumber' => 'Integer',
		'Dnumber' => 'Float',
		'String' => 'Identifier',
		'DoubleCast' => 'FloatCast',
		'PaamayimNekudotayim' => 'DoubleColon',
		'NsSeparator' => 'NamespaceSeparator',
		'Sl' => 'ShiftLeft',
		'Sr' => 'ShiftRight',
		'SlEqual' => 'ShiftLeftEqual',
		'SrEqual' => 'ShiftRightEqual',
		'Inc' => 'Increment',
		'Dec' => 'Decrement',
		'MulEqual' => 'MultiplyEqual',
		'DivEqual' => 'DivideEqual',
		'ModEqual' => 'ModuloEqual',
		'Pow' => 'Power',
		'PowEqual' => 'PowerEqual',
		'NumString' => 'NumericString',
		'StringVarname' => 'StringVariableName',
		'AmpersandFollowedByVarOrVararg' => 'AmpersandFollowedByVariableOrVariadic',
		'AmpersandNotFollowedByVarOrVararg' => 'AmpersandNotFollowedByVariableOrVariadic',
		'Class' => 'ClassKeyword', // PHP reserves class for ::class, and namespace from 8.6
		'Namespace' => 'NamespaceKeyword',
		'ClassC' => 'MagicClass',
		'TraitC' => 'MagicTrait',
		'MethodC' => 'MagicMethod',
		'FuncC' => 'MagicFunction',
		'PropertyC' => 'MagicProperty',
		'NsC' => 'MagicNamespace',
		'Line' => 'MagicLine',
		'File' => 'MagicFile',
		'Dir' => 'MagicDir',
	];

	$kind = str_replace('_', '', ucwords(strtolower(substr($token, 2)), '_'));
	return $renames[$kind] ?? $kind;
}


/**
 * The TokenData trait: a kind is the id PhpToken gives the token, so a kind of the grammar is its T_* constant;
 * a token the running PHP does not know yet gets a negative kind of its own, and so do the kinds PHP has none of.
 * @param array<string, int> $tokens
 */
function buildTokenData(array $tokens): string
{
	$emulated = ['T_VOID_CAST' => 80500, 'T_PIPE' => 80500]; // PHP tokenizes them from the version, an emulator before it
	$kinds = ['EndOfFile' => '0'];
	$own = 0;
	foreach (array_keys($tokens) as $token) {
		$kinds[nameKind($token)] = isset($emulated[$token])
			? "PHP_VERSION_ID >= $emulated[$token] ? $token : " . --$own
			: $token;
	}

	$kinds += ['CloseTag' => 'T_CLOSE_TAG', 'OpenTagWithEcho' => 'T_OPEN_TAG_WITH_ECHO', 'HaltCompilerData' => (string) --$own];

	$constants = fn(array $kinds) => implode(",\n", array_map(fn($kind, $value) => "\t\t$kind = $value", array_keys($kinds), $kinds));

	return str_replace("\r\n", "\n", <<<PHP
		<?php declare(strict_types=1);

		/**
		 * @generated by grammar/build.php from grammar/php.y, do not edit.
		 */

		namespace PhpSyntax;


		/**
		 * Kinds of tokens, the constants of `Token`: the id `PhpToken` gives a token, so a single-character token
		 * has the ordinal of the character. A token the running PHP does not tokenize yet has a negative kind the
		 * emulator gives it, and so do the kinds PHP has none of. A kind differs between PHP versions, so it is
		 * never stored, only compared. A keyword is named by the word itself, except `class`, which PHP reserves
		 * for `::class`, and `namespace`, which PHP reserves from 8.6.
		 * @internal the constants are read as those of `Token`
		 */
		trait TokenData
		{
			public const
		{$constants($kinds)};
		}

		PHP);
}


/**
 * Keys the table of token kinds to symbols by the kinds: phpyacc numbers the tokens itself, while a kind is
 * the id of the running PHP, so the table names them. A single character stays keyed by its ordinal.
 * @param array<string, int> $tokens
 */
function keyTokenTable(string $code, array $tokens): string
{
	$pattern = '~(\tprotected const TokenToSymbol = \[\n)(.*?)(\n\t\];)~s';
	preg_match($pattern, $code, $m) || throw new Exception('The table TokenToSymbol was not found.');
	$symbols = array_map(intval(...), preg_split('~[\s,]+~', trim($m[2]), -1, PREG_SPLIT_NO_EMPTY));
	$lines = array_map(fn(array $row) => "\t\t" . implode(', ', $row) . ',', array_chunk(array_slice($symbols, 0, 256), 10));
	foreach ($tokens as $token => $number) {
		$lines[] = "\t\tToken::" . nameKind($token) . ' => ' . $symbols[$number] . ',';
	}

	return str_replace($m[0], $m[1] . implode("\n", $lines) . $m[3], $code);
}
