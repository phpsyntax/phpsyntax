<?php declare(strict_types=1);

/**
 * Ported from Latte grammar/rebuildParsers.php (https://latte.nette.org), itself a port of nikic/php-parser grammar/rebuildParsers.php.
 *
 * Generates src/ParserData.php and src/TokenKind.php from grammar/php.y.
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
checkActionCoverage($grammar);
$grammar = preprocessGrammar($grammar);
file_put_contents($tmpGrammarFile, $grammar);

echo "Building parser.\n";
execCmd($phpyacc, ...($optionDebug ? ['-t', '-v'] : []), ...['-m', __DIR__ . '/parser.template', '-p', 'ParserData', $tmpGrammarFile]);
$code = file_get_contents($tmpResultFile);
$code = removeTrailingWhitespace($code);
$code = optimize($code);
file_put_contents("$srcDir/ParserData.php", $code);
unlink($tmpResultFile);

echo "Building token kinds.\n";
execCmd($phpyacc, '-m', __DIR__ . '/tokens.template', $tmpGrammarFile);
$code = buildTokenKind(file_get_contents($tmpResultFile));
file_put_contents("$srcDir/TokenKind.php", $code);
unlink($tmpResultFile);

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
function execCmd(string $script, string ...$args): void
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
 * Turns the "T_NAME = number" list produced by tokens.template into the TokenKind class.
 */
function buildTokenKind(string $list): string
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
		'Class' => 'ClassKeyword', // PHP does not allow either as a class constant name
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

	preg_match_all('~^(T_\w+) = (\d+)$~m', $list, $matches, PREG_SET_ORDER);
	$kinds = ['EndOfFile' => 0];
	$hosts = [];
	$last = 0;
	foreach ($matches as [, $name, $number]) {
		$kind = str_replace('_', '', ucwords(strtolower(substr($name, 2)), '_'));
		$kind = $renames[$kind] ?? $kind;
		$kinds[$kind] = (int) $number;
		$hosts[$name] = $kind;
		$last = max($last, (int) $number);
	}

	foreach (['CloseTag', 'OpenTagWithEcho', 'HaltCompilerData'] as $kind) {
		$kinds[$kind] = ++$last;
	}

	$raw = [];
	foreach (['Whitespace', 'Comment', 'DocComment', 'OpenTag'] as $kind) {
		$raw[$kind] = ++$last;
	}

	$hosts += [
		'T_CLOSE_TAG' => 'CloseTag',
		'T_OPEN_TAG_WITH_ECHO' => 'OpenTagWithEcho',
		'T_WHITESPACE' => 'Whitespace',
		'T_COMMENT' => 'Comment',
		'T_DOC_COMMENT' => 'DocComment',
		'T_OPEN_TAG' => 'OpenTag',
	];

	$constants = fn(array $kinds) => implode(",\n", array_map(fn($kind, $number) => "\t\t$kind = $number", array_keys($kinds), $kinds));
	$hostConstants = implode("\n", array_map(fn($name, $kind) => "\t\t'$name' => self::$kind,", array_keys($hosts), $hosts));

	return str_replace("\r\n", "\n", <<<PHP
		<?php declare(strict_types=1);

		/**
		 * @generated by grammar/build.php from grammar/php.y, do not edit.
		 */

		namespace PhpSyntax;


		/**
		 * Kinds of tokens; single-character tokens use the ordinal of the character as their kind. A keyword is
		 * named by the word itself, except class and namespace, which PHP does not allow as class constant names.
		 */
		final class TokenKind
		{
			public const
		{$constants($kinds)};

			/** raw kinds between the tokenizer and trivia folding; they become trivia and never reach the parser */
			public const
		{$constants($raw)};

			/** PhpToken ids (T_* constant names) to kinds */
			public const HostConstants = [
		$hostConstants
			];
		}

		PHP);
}


/**
 * Every alternative with two or more symbols must have an action that uses every symbol, otherwise tokens
 * would silently drop out of the tree; a single symbol passes through by default.
 */
function checkActionCoverage(string $grammar): void
{
	$body = explode("\n%%\n", $grammar)[1];
	$body = preg_replace(regex('(?&string)(*SKIP)(*FAIL)|(?&code)(*SKIP)(*FAIL)|/\*.*?\*/'), '', $body);
	$errors = [];
	foreach (preg_split('~^(?=\w+:)~m', $body) as $chunk) {
		if (!preg_match('~^(\w+):\s*(.*?)\s*;?\s*$~s', $chunk, $m)) {
			continue;
		}

		[, $name, $content] = $m;
		$alternatives = preg_split(regex('(?&string)(*SKIP)(*FAIL)|(?&code)(*SKIP)(*FAIL)|\|'), $content);
		foreach ($alternatives as $alternative) {
			$action = preg_match(regex('(?&code)\s*$'), $alternative, $am) ? $am[0] : null;
			$symbols = preg_split('~\s+~', trim($action === null ? $alternative : substr($alternative, 0, -strlen($am[0]))), -1, PREG_SPLIT_NO_EMPTY);
			if (($index = array_search('%prec', $symbols, strict: true)) !== false) {
				array_splice($symbols, $index, 2);
			}

			if (count($symbols) < 2) {
				continue;
			} elseif ($action === null) {
				$errors[] = "$name: '" . implode(' ', $symbols) . "' has no action";
				continue;
			}

			foreach ($symbols as $i => $symbol) {
				if (!preg_match('~\$' . ($i + 1) . '\b~', $action)) {
					$errors[] = "$name: '" . implode(' ', $symbols) . "' does not use \$" . ($i + 1) . " ($symbol)";
				}
			}
		}
	}

	if ($errors) {
		echo "Action coverage errors:\n  " . implode("\n  ", $errors) . "\n";
		exit(1);
	}
}
