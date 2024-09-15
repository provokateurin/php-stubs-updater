#!/bin/env php
<?php

declare(strict_types=1);

include __DIR__ . '/vendor/autoload.php';

use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

function isStmtRelevant(Stmt $stmt): bool {
	return $stmt instanceof Class_ || $stmt instanceof Interface_ || $stmt instanceof Trait_;
}

function generateStub(array &$expectedStubs, string $dir, PrettyPrinter $prettyPrinter, array $newStmts, array $oldStmts, array $oldTokens, Stmt $stmt): void {
	$className = $stmt->namespacedName->toString();
	$stubName = strtolower(str_replace('\\', '_', $className));
	if (!in_array($stubName, $expectedStubs, true)) {
		return;
	}

	$expectedStubs = array_diff($expectedStubs, [$stubName]);

	error_log('Updating stub ' . $stubName);

	foreach ($stmt->stmts as $i => $subStmt) {
		if (!$subStmt instanceof ClassMethod && !$subStmt instanceof Property) {
			continue;
		}

		if ($subStmt->isPrivate()) {
			unset($stmt->stmts[$i]);
		} else {
			$subStmt->stmts = [];
			$stmt->stmts[$i] = $subStmt;
		}
	}

	foreach ($newStmts as $i => $newStmt) {
		if ($newStmt instanceof Stmt\Expression && $newStmt->expr instanceof Include_) {
			unset($newStmts[$i]);
		}
	}

	file_put_contents($dir . '/' . $stubName . '.php', $prettyPrinter->printFormatPreserving($newStmts, $oldStmts, $oldTokens));
}

$parser = (new ParserFactory())->createForHostVersion();

$nodeTraverser = new NodeTraverser();
$nodeTraverser->addVisitor(new CloningVisitor());
$nodeTraverser->addVisitor(new NameResolver());

$prettyPrinter = new PrettyPrinter\Standard();

if ($argc < 3) {
	throw new InvalidArgumentException('Usage: update-stubs.php [destination folder] [source folders]');
}
$dest = $argv[1];
$sources = array_slice($argv, 2);

$expectedStubs = [];
foreach (new DirectoryIterator($dest) as $info) {
	if ($info->getType() !== 'file' || $info->getExtension() !== 'php') {
		continue;
	}

	$name = $info->getFilename();
	$name = substr($name, 0, -4);
	$expectedStubs[] = $name;
}

foreach ($sources as $source) {
	error_log('Generating stubs from ' . $source);

	/** @var SplFileInfo $info */
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source)) as $info) {
		if ($info->getType() !== 'file' || $info->getExtension() !== 'php') {
			continue;
		}

		$code = file_get_contents($info->getRealPath());
		$oldStmts = $parser->parse($code);
		$oldTokens = $parser->getTokens();
		$newStmts = $nodeTraverser->traverse($oldStmts);

		/** @var Stmt $stmt1 */
		foreach ($newStmts as $stmt1) {
			if (isStmtRelevant($stmt1)) {
				generateStub($expectedStubs, $dest, $prettyPrinter, $newStmts, $oldStmts, $oldTokens, $stmt1);
			} elseif (!$stmt1 instanceof Namespace_) {
				continue;
			}

			foreach ($stmt1->stmts as $stmt2) {
				if (!isStmtRelevant($stmt2)) {
					continue;
				}

				generateStub($expectedStubs, $dest, $prettyPrinter, $newStmts, $oldStmts, $oldTokens, $stmt2);
			}
		}
	}
}

if (count($expectedStubs) > 0) {
    throw new RuntimeException('Unable to update the following stubs: '. implode(', ', $expectedStubs).'. Please make sure you included all the relevant source directories.');
}
