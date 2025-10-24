<?php

declare(strict_types=1);

use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;
use PhpParser\Error;
use PhpParser\NodeVisitor;

// Simple php-parser benchmark runnable from src/WithCache or src/WithoutCache via:
//   cd src/WithCache && php ../test.php
//   cd src/WithoutCache && php ../test.php

@ini_set('memory_limit', '-1');

$cwd = getcwd() ?: __DIR__;
$mode = basename($cwd); // WithCache / WithoutCache

require_once __DIR__ . DIRECTORY_SEPARATOR  . $mode . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (! class_exists(\PhpParser\ParserFactory::class)) {
	fwrite(STDERR, "Could not locate Composer autoload or php-parser.\n");
	fwrite(STDERR, "Ensure you run from src/WithCache or src/WithoutCache.\n");
	exit(1);
}
/**
 * Custom traverser that caches visited nodes across passes and skips traversing
 * children for nodes already seen. Both directories use the same visitors; only
 * the traverser behavior differs when enabled.
 */
final class CachingNodeTraverser extends NodeTraverser
{
	/**
	 * Cache visitors per node class name, similar to Rector's traverser.
	 * @var array<string, array<int, NodeVisitor>>
	 */
	private array $visitorsByClass = [];

	/**
	 * Cache and return only the visitors applicable to this node class.
	 * A visitor can opt-in by implementing an "appliesTo(Node $node): bool" method.
	 * If the method is absent, the visitor is considered applicable to all nodes.
	 *
	 * @return NodeVisitor[]
	 */
	public function getVisitorsForNode(Node $node)
	{
		if (isset($this->visitorsByClass[$node::class])) {
			return $this->visitorsByClass[$node::class];
		}

		foreach ($this->visitors as $visitor) {
			if ($visitor instanceof SomeVisitorAppInterface) {
				foreach ($visitor->getNodeTypes() as $nodeType) {
					if (is_a($node, $nodeType, true)) {
						$this->visitorsByClass[$node::class][] = $visitor;
						continue 2;
					}
				}
			}
		}

		return $this->visitorsByClass[$node::class] ?? $this->visitors;
	}
}

/**
 * Recursively collect PHP files from a directory.
 * @return array<int, string> absolute file paths
 */
function collectPhpFiles(string $dir): array
{
	$files = [];
	if (!is_dir($dir)) {
		return $files;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator(
			$dir,
			FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
		)
	);

	foreach ($iterator as $fileInfo) {
		if (!$fileInfo->isFile()) {
			continue;
		}
		$path = $fileInfo->getPathname();
		if (substr($path, -4) === '.php') {
			$files[] = $path;
		}
	}
	sort($files);
	return $files;
}

function formatBytes(int $bytes): string
{
	$units = ['B', 'KB', 'MB', 'GB'];
	$i = 0;
	$size = (float) $bytes;
	while ($size >= 1024 && $i < count($units) - 1) {
		$size /= 1024;
		$i++;
	}
	return number_format($size, 2) . ' ' . $units[$i];
}

// 3) Collect files and parse them once to ASTs
$files = collectPhpFiles($cwd . '/vendor/rector/rector/src');
$files = array_merge($files, collectPhpFiles($cwd . '/vendor/rector/rector/rules'));

if ($files === []) {
	fwrite(STDERR, "No PHP files found under: {$targetDir}\n");
	exit(1);
}

$iterations = 100;

echo "php-parser visitor benchmark\n";
echo "Mode            : {$mode}\n";
echo str_repeat('-', 60) . "\n";

$parserFactory = new ParserFactory();
$parser = $parserFactory->createForNewestSupportedVersion();

$asts = [];
$totalSourceBytes = 0;
foreach ($files as $path) {
	$code = @file_get_contents($path);
	if ($code === false) {
		continue;
	}
	$totalSourceBytes += strlen($code);
	try {
		$stmts = $parser->parse($code);
	} catch (Error $e) {
		// Skip files with parse errors
		continue;
	}
	if ($stmts !== null) {
		$asts[] = $stmts;
	}
}

if ($asts === []) {
	fwrite(STDERR, "Parsing produced no ASTs (all files skipped?).\n");
	exit(1);
}

interface SomeVisitorAppInterface
{
}

// Example visitor that only applies to a specific node type, to demonstrate caching
final class FunctionOnlyVisitor extends NodeVisitorAbstract implements SomeVisitorAppInterface
{
	/**
	 * @return array<int, class-string<Node>>
	 */
	public function getNodeTypes(): array
	{
		return [\PhpParser\Node\Stmt\Function_::class];
	}

	public function enterNode(Node $node)
	{
		return null;
	}
}

// Example visitor that only applies to a specific node type, to demonstrate caching
final class ClassOnlyVisitor extends NodeVisitorAbstract implements SomeVisitorAppInterface
{
	/**
	 * @return array<int, class-string<Node>>
	 */
	public function getNodeTypes(): array
	{
		return [\PhpParser\Node\Stmt\Class_::class];
	}

	public function enterNode(Node $node)
	{
		return null;
	}
}

// Example visitor that only applies to a specific node type, to demonstrate caching
final class ClassMethodOnlyVisitor extends NodeVisitorAbstract implements SomeVisitorAppInterface
{
	/**
	 * @return array<int, class-string<Node>>
	 */
	public function getNodeTypes(): array
	{
		return [\PhpParser\Node\Stmt\ClassMethod::class];
	}

	public function enterNode(Node $node)
	{
		return null;
	}
}

final class CountingVisitor extends NodeVisitorAbstract implements SomeVisitorAppInterface
{
	public int $nodesVisited = 0;

	public function getNodeTypes(): array
	{
		return [Node::class];
	}

	public function enterNode(Node $node)
	{
		// Count every node visited. Returning null keeps traversal unchanged.
		$this->nodesVisited++;
		return null;
	}
}

$visitors = [
	new FunctionOnlyVisitor(),
	new ClassOnlyVisitor(),
	new ClassMethodOnlyVisitor(),
	$counter = new CountingVisitor(),
];

// Build traverser once (so cache persists across passes) and traverse ASTs
$traverser = ($mode === 'WithCache') ? new CachingNodeTraverser(...$visitors) : new NodeTraverser(...$visitors);

$onePass = function () use ($traverser, $counter, $asts): array {
	// reset counter for this pass
	$counter->nodesVisited = 0;
	foreach ($asts as $stmts) {
		$traverser->traverse($stmts);
	}

	return ['visited' => $counter->nodesVisited];
};

// Measured passes
$visitedTotal = 0;
$t0 = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
	$result = $onePass();
	$visitedTotal += (int) $result['visited'];
}
$t1 = hrtime(true);

$elapsedNs = $t1 - $t0;
$elapsedMs = $elapsedNs / 1_000_000;

$peakMem = memory_get_peak_usage(true);

echo "Nodes visited   : " . number_format($visitedTotal) . "\n";
echo "Total time      : " . number_format($elapsedMs, 2) . " ms\n";
echo "Peak memory     : " . formatBytes($peakMem) . "\n";
echo str_repeat('=', 60) . "\n";

// Exit success
exit(0);
