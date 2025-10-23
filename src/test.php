<?php
declare(strict_types=1);

// Simple php-parser benchmark runnable from src/WithCache or src/WithoutCache via:
//   cd src/WithCache && php ../test.php
//   cd src/WithoutCache && php ../test.php

// 1) Locate Composer autoload from the current working directory first (WithCache/WithoutCache)
$autoloadCandidates = [
	getcwd() . '/vendor/autoload.php',
	__DIR__ . '/vendor/autoload.php',
	__DIR__ . '/WithCache/vendor/autoload.php',
	__DIR__ . '/WithoutCache/vendor/autoload.php',
];

$autoloadLoaded = false;
foreach ($autoloadCandidates as $autoload) {
	if (is_file($autoload)) {
		require $autoload;
		$autoloadLoaded = true;
		break;
	}
}

if (!$autoloadLoaded || !class_exists(\PhpParser\ParserFactory::class)) {
	fwrite(STDERR, "Could not locate Composer autoload or php-parser.\n");
	fwrite(STDERR, "Ensure you run from src/WithCache or src/WithoutCache.\n");
	exit(1);
}

use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;
use PhpParser\Error;
use PhpParser\NodeVisitor;

final class CountingVisitor extends NodeVisitorAbstract
{
	public int $nodesVisited = 0;

	public function enterNode(Node $node)
	{
		// Count every node visited. Returning null keeps traversal unchanged.
		$this->nodesVisited++;
		return null;
	}
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
		$class = $node::class;
		if (isset($this->visitorsByClass[$class])) {
			return $this->visitorsByClass[$class];
		}

		$applicable = [];
		foreach ($this->visitors as $visitor) {
			if (method_exists($visitor, 'appliesTo')) {
				// Optional applicability hook; treat falsey as not applicable.
				/** @var callable $call */
				$call = [$visitor, 'appliesTo'];
				if (!\is_callable($call) || (bool) $call($node) !== true) {
					continue;
				}
			}
			$applicable[] = $visitor;
		}

		$this->visitorsByClass[$class] = $applicable;
		return $applicable;
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

// 2) Determine target directory to parse
$cwd = getcwd() ?: __DIR__;
$defaultTargets = [
	$cwd . '/vendor/nikic/php-parser/lib/PhpParser',
	$cwd . '/vendor/nikic/php-parser/lib',
	$cwd . '/vendor',
];

$targetDir = null;
foreach ($defaultTargets as $candidate) {
	if (is_dir($candidate)) {
		$targetDir = $candidate;
		break;
	}
}

if ($targetDir === null) {
	fwrite(STDERR, "No target directory found to parse.\n");
	exit(1);
}

// 3) Collect files and parse them once to ASTs
$files = collectPhpFiles($targetDir);
if ($files === []) {
	fwrite(STDERR, "No PHP files found under: {$targetDir}\n");
	exit(1);
}

$iterations = 100;

$mode = basename($cwd); // WithCache / WithoutCache

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

// proceed to measured traversal output only

// 4) Build traverser once (so cache persists across passes) and traverse ASTs
$traverser = ($mode === 'WithCache') ? new CachingNodeTraverser() : new NodeTraverser();
$resolver = new NameResolver(null, ['preserveOriginalNames' => true]);
$traverser->addVisitor($resolver);

// Example visitor that only applies to a specific node type, to demonstrate caching
final class FunctionOnlyVisitor extends NodeVisitorAbstract
{
	public function appliesTo(Node $node): bool
	{
		return $node instanceof \PhpParser\Node\Stmt\Function_;
	}

	public function enterNode(Node $node)
	{
		return null;
	}
}

$functionOnlyVisitor = new FunctionOnlyVisitor();
$traverser->addVisitor($functionOnlyVisitor);

$counter = new CountingVisitor();
$traverser->addVisitor($counter);

$onePass = function () use ($traverser, $counter): array {
	// reset counter for this pass
	$counter->nodesVisited = 0;
	foreach ($GLOBALS['__ASTS'] as $stmts) {
		$traverser->traverse($stmts);
	}
	return ['visited' => $counter->nodesVisited];
};

// expose ASTs for the closure
$GLOBALS['__ASTS'] = $asts;

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

// Cleanup global
unset($GLOBALS['__ASTS']);

// Exit success
exit(0);
