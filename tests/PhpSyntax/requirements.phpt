<?php declare(strict_types=1);

/**
 * The library runs on what composer.json asks for: PHP itself and ext-tokenizer, nothing else.
 */

use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Parser;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('src calls no function of an extension that is not required', function () {
	$bundled = ['Core', 'standard', 'pcre', 'SPL', 'tokenizer'];
	$provided = [];
	foreach (get_loaded_extensions() as $extension) {
		foreach (get_extension_funcs($extension) ?: [] as $function) {
			$provided[strtolower($function)] = $extension;
		}
	}

	// the parser reads its own sources; a call made through a variable or a class is not among them
	$parser = new Parser;
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../src', FilesystemIterator::SKIP_DOTS));
	foreach ($files as $file) {
		if ($file->getExtension() !== 'php') {
			continue;
		}

		foreach ($parser->parse((string) file_get_contents($file->getPathname()))->find(FunctionCallNode::class) as $call) {
			if (!$call->name instanceof NameNode) {
				continue;
			}

			$name = strtolower(implode('\\', $call->name->parts));
			$extension = $provided[$name] ?? 'no loaded extension';
			Assert::contains($extension, $bundled, "$name() in {$file->getFilename()} comes from $extension");
		}
	}
});
