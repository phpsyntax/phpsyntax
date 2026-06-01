<?php declare(strict_types=1);

/**
 * Copied from Latte grammar/phpyLang.php (https://latte.nette.org), itself a port of nikic/php-parser grammar/phpyLang.php.
 */

///////////////////////////////
/// Utility regex constants ///
///////////////////////////////

const LIB = '(?(DEFINE)
    (?<singleQuotedString>\'[^\\\\\']*+(?:\\\.[^\\\\\']*+)*+\')
    (?<doubleQuotedString>"[^\\\"]*+(?:\\\.[^\\\"]*+)*+")
    (?<string>(?&singleQuotedString)|(?&doubleQuotedString))
    (?<comment>/\*[^*]*+(?:\*(?!/)[^*]*+)*+\*/)
    (?<code>\{[^\'"/{}]*+(?:(?:(?&string)|(?&comment)|(?&code)|/)[^\'"/{}]*+)*+})
)';

const PARAMS = '\[(?<params>[^[\]]*+(?:\[(?&params)\][^[\]]*+)*+)\]';
const ARGS   = '\((?<args>[^()]*+(?:\((?&args)\)[^()]*+)*+)\)';

///////////////////////////////
/// Preprocessing functions ///
///////////////////////////////

function preprocessGrammar($code)
{
	$code = resolveNodes($code);
	$code = resolveMacros($code);
	$code = resolveStackAccess($code);

	return $code;
}


function resolveNodes($code)
{
	return preg_replace_callback(
		'~\b(?<name>[A-Z][a-zA-Z_\\\]++)\s*' . PARAMS . '~',
		function ($matches) {
			// recurse
			$matches['params'] = resolveNodes($matches['params']);

			$params = magicSplit(
				'(?:' . PARAMS . '|' . ARGS . ')(*SKIP)(*FAIL)|,',
				$matches['params'],
			);

			$paramCode = '';
			foreach ($params as $param) {
				$paramCode .= $param . ', ';
			}

			return 'new ' . $matches['name'] . 'Node(' . $paramCode . 'line())';
		},
		$code,
	);
}


function resolveMacros($code)
{
	return preg_replace_callback(
		'~\b(?<!::|->)(?!array\()(?<name>[a-z][A-Za-z]++)' . ARGS . '~',
		function ($matches) {
			// recurse
			$matches['args'] = resolveMacros($matches['args']);

			$name = $matches['name'];
			$args = magicSplit(
				'(?:' . PARAMS . '|' . ARGS . ')(*SKIP)(*FAIL)|,',
				$matches['args'],
			);

			if ($name === 'line') {
				assertArgs(0, $args, $name);
				return '$this->startPos(#1), $this->endPos($pos)';
			}

			if ($name === 'stackLine') {
				assertArgs(1, $args, $name);
				return '$this->startTokenStack[' . $args[0] . ']->position';
			}

			if ($name === 'init') {
				return '$$ = [' . implode(', ', $args) . ']';
			}

			if ($name === 'push') {
				assertArgs(2, $args, $name);

				return $args[0] . '[] = ' . $args[1] . '; $$ = ' . $args[0];
			}

			if ($name === 'parseVar') {
				assertArgs(1, $args, $name);

				return 'substr(' . $args[0] . ', 1)';
			}

			return $matches[0];
		},
		$code,
	);
}


function assertArgs($num, $args, $name)
{
	if ($num != count($args)) {
		die('Wrong argument count for ' . $name . '().');
	}
}


function resolveStackAccess($code)
{
	$code = preg_replace('/\$\d+/', '$this->semStack[$0]', $code);
	$code = preg_replace('/#(\d+)/', '$$1', $code);
	return $code;
}


function removeTrailingWhitespace($code)
{
	$lines = explode("\n", $code);
	$lines = array_map('rtrim', $lines);
	return implode("\n", $lines);
}


function optimize($s)
{
	$s = str_replace("\t $", "\t$", $s);

	$s = preg_replace_callback('~\$pos-\((\d+)-(\d+)\)~', function ($m) {
		$i = $m[1] - $m[2];
		return '$pos' . ($i === 0 ? '' : ' - ' . ($m[1] - $m[2]));
	}, $s);


	// rules with the same action share one body under stacked case labels
	$eol = str_contains($s, "\r\n") ? "\r\n" : "\n";
	$pattern = '~[ \t]+case (\d+):\r?\n(.*\r?\n)\t\t\t\tbreak;\r?\n~Us';
	$all = [];
	preg_match_all($pattern, $s, $matches, PREG_SET_ORDER);
	foreach ($matches as [, $id, $code]) {
		$all[$code][] = $id;
	}

	$done = [];
	return preg_replace_callback($pattern, function ($m) use ($all, $eol, &$done) {
		[, , $code] = $m;
		if (isset($done[$code])) {
			return '';
		}

		$done[$code] = true;
		return implode('', array_map(fn($id) => "\t\t\tcase $id:$eol", $all[$code]))
			. str_replace('; ', ";$eol\t\t\t\t", $code)
			. "\t\t\t\tbreak;$eol";
	}, $s);
}


//////////////////////////////
/// Regex helper functions ///
//////////////////////////////

function regex($regex)
{
	return '~' . LIB . '(?:' . str_replace('~', '\~', $regex) . ')~';
}


function magicSplit($regex, $string)
{
	$pieces = preg_split(regex('(?:(?&string)|(?&comment)|(?&code))(*SKIP)(*FAIL)|' . $regex), $string);

	foreach ($pieces as &$piece) {
		$piece = trim($piece);
	}

	if ($pieces === ['']) {
		return [];
	}

	return $pieces;
}
