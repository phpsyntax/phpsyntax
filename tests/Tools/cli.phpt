<?php declare(strict_types=1);

/**
 * The command line tool: the exit code is the verdict, the payload goes to standard output and what tells
 * the reader where they are to the error one.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


/**
 * Runs the tool from the root of the project, so that a path it prints is the one that was passed.
 * @param  list<string>  $arguments
 * @return array{out: string, err: string, code: int}
 */
function runTool(array $arguments, ?string $stdin = null): array
{
	$root = __DIR__ . '/../..';
	$descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
	$process = proc_open([PHP_BINARY, "$root/bin/phpsyntax", ...$arguments], $descriptors, $pipes, cwd: $root);
	if ($process === false) {
		throw new RuntimeException('Cannot run the tool.');
	}

	fwrite($pipes[0], (string) $stdin);
	fclose($pipes[0]);
	$out = (string) stream_get_contents($pipes[1]); // before the error output, which stays short
	$err = (string) stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	return ['out' => $out, 'err' => $err, 'code' => proc_close($process)];
}


test('check passes over a file, a directory and standard input', function () {
	$file = runTool(['check', 'src/Style.php']);
	Assert::same(0, $file['code']);
	Assert::contains('ok src/Style.php', $file['err']);

	$directory = runTool(['check', '--quiet', 'src/Analyses']);
	Assert::same(0, $directory['code']);
	Assert::contains('3 read, 0 failed', $directory['err']);

	$stdin = runTool(['check', '-'], '<?php echo 1;');
	Assert::same(0, $stdin['code']);

	$nothing = runTool(['check', '--ext=nope', 'src/Analyses']);
	Assert::same(2, $nothing['code']);
	Assert::contains('Nothing to read', $nothing['err']);
});


test('check fails on code the parser refuses, unless --lint finds PHP refuses it too', function () {
	$refused = runTool(['check', '--eval=<?php $a = ;']);
	Assert::same(1, $refused['code']);
	Assert::match('<argument>:1:12  Unexpected%A%', $refused['out']);

	$linted = runTool(['check', '--lint', '--eval=<?php $a = ;']);
	Assert::same(0, $linted['code']);
});


test('dump writes the tree to standard output and the name of the input to the error one', function () {
	$result = runTool(['dump', '--no-trivia', '--as=expression', '--eval=$a + 1']);
	Assert::same(0, $result['code']);
	Assert::match(<<<'XX'
		BinaryOpNode
		  left: VariableNode
		    name: Variable "$a"
		  operator: '+' "+"
		  right: IntegerNode
		    token: Integer "1"

		XX, $result['out']);
	Assert::contains('==> <argument>', $result['err']);
});


test('dump --at names the node at the position and the ancestors it stands in', function () {
	$result = runTool(['dump', '--at=2:3', '--no-trivia', "--eval=<?php\n\$total = 1;"]);
	Assert::same(0, $result['code']);
	Assert::match(<<<'XX'
		VariableNode
		  name: Variable "$total"

		XX, $result['out']);
	Assert::contains('FileNode > NodeList > ExpressionStatementNode > AssignmentNode > VariableNode', $result['err']);

	$beyond = runTool(['dump', '--at=9:1', "--eval=<?php\n\$total = 1;"]);
	Assert::same(2, $beyond['code']);
	Assert::contains('no line 9', $beyond['err']);
});


test('find tells a hit from nothing found by the exit code', function () {
	$hit = runTool(['find', 'NamedTypeNode', '--eval=<?php function f(?int $qty) {}']);
	Assert::same(0, $hit['code']);
	Assert::match('<argument>:1:%d%  "int"%A%', $hit['out']);

	$nothing = runTool(['find', 'MatchNode', '--eval=<?php function f() {}']);
	Assert::same(1, $nothing['code']);
	Assert::same('', $nothing['out']);
});


test('a wrong invocation ends with two and says what is wrong', function () {
	foreach ([[], ['fly'], ['dump', '--nope', 'src/Style.php'], ['find', 'NoSuchNode', 'src/Style.php']] as $arguments) {
		$result = runTool($arguments);
		Assert::same(2, $result['code'], implode(' ', $arguments));
		Assert::contains('Usage: phpsyntax', $result['err']);
	}
});
