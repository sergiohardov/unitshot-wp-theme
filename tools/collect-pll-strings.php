#!/usr/bin/env php
<?php

/**
 * Collect Polylang translation strings from theme files.
 *
 * Scans all PHP/JS files in the theme root (excluding vendor, sources/node_modules)
 * and extracts string literals from pll__(), pll_e(), pll_esc_html__(), pll_esc_attr__().
 *
 * Usage:  php tools/collect-pll-strings.php [--output=path/to/output.json]
 */

define('THEME_ROOT', realpath(__DIR__ . '/..'));
define('DEFAULT_OUTPUT', THEME_ROOT . '/languages/pll-strings.json');

// --------------------------------------------------------------------------
// Config
// --------------------------------------------------------------------------

/** Directories to skip (relative to THEME_ROOT, compared by real path prefix). */
const SKIP_DIRS = [
    'vendor',
    'sources',
    'tools',
];

/** File extensions to scan. */
const SCAN_EXTENSIONS = ['php', 'js', 'jsx', 'ts', 'tsx', 'twig'];

/** Polylang functions whose first argument is a translatable string. */
const PLL_FUNCTIONS = [
    'pll__',
    'pll_e',
    'pll_esc_html__',
    'pll_esc_html_e',
    'pll_esc_attr__',
    'pll_esc_attr_e',
];

// --------------------------------------------------------------------------
// CLI args
// --------------------------------------------------------------------------

$outputFile = DEFAULT_OUTPUT;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--output=')) {
        $outputFile = substr($arg, strlen('--output='));
    }
}

// --------------------------------------------------------------------------
// Helpers
// --------------------------------------------------------------------------

function shouldSkipDir(string $realPath): bool
{
    foreach (SKIP_DIRS as $rel) {
        $skip = realpath(THEME_ROOT . '/' . $rel);
        if ($skip && str_starts_with($realPath, $skip . DIRECTORY_SEPARATOR)) {
            return true;
        }
        if ($skip && $realPath === $skip) {
            return true;
        }
    }
    return false;
}

function hasValidExtension(string $file): bool
{
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return in_array($ext, SCAN_EXTENSIONS, true);
}

/**
 * Extract all translatable strings from file content.
 * Matches single-quoted and double-quoted string literals.
 */
function extractStrings(string $content): array
{
    $functions = implode('|', array_map('preg_quote', PLL_FUNCTIONS, array_fill(0, count(PLL_FUNCTIONS), '/')));

    // Matches: pll__( 'string' ) or pll__( "string" )
    // Handles escaped quotes inside the string.
    $pattern = '/(?:' . $functions . ')\s*\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/u';

    $found = [];

    if (preg_match_all($pattern, $content, $matches)) {
        foreach ($matches[1] as $i => $singleQuoted) {
            $raw = $singleQuoted !== '' ? $singleQuoted : $matches[2][$i];
            $string = stripslashes($raw);
            if ($string !== '') {
                $found[] = $string;
            }
        }
    }

    return $found;
}

/**
 * Recursively collect all files to scan.
 *
 * @return SplFileInfo[]
 */
function collectFiles(string $dir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            function (SplFileInfo $item) {
                if ($item->isDir() && shouldSkipDir($item->getRealPath())) {
                    return false;
                }
                return true;
            }
        ),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && hasValidExtension($file->getFilename())) {
            $files[] = $file;
        }
    }

    return $files;
}

// --------------------------------------------------------------------------
// Main
// --------------------------------------------------------------------------

echo "Scanning theme: " . THEME_ROOT . PHP_EOL;

$files   = collectFiles(THEME_ROOT);
$strings = [];
$stats   = ['files_scanned' => 0, 'files_with_strings' => 0];

foreach ($files as $file) {
    $content = file_get_contents($file->getRealPath());
    if ($content === false) {
        fwrite(STDERR, "Warning: cannot read {$file->getRealPath()}" . PHP_EOL);
        continue;
    }

    $found = extractStrings($content);
    $stats['files_scanned']++;

    if (!empty($found)) {
        $stats['files_with_strings']++;
        $relativePath = ltrim(str_replace(THEME_ROOT, '', $file->getRealPath()), DIRECTORY_SEPARATOR . '/');
        echo "  [{$relativePath}] — " . count($found) . " string(s)" . PHP_EOL;
        foreach ($found as $s) {
            $strings[] = $s;
        }
    }
}

// Deduplicate and sort for a stable, readable output.
$strings = array_values(array_unique($strings));
sort($strings, SORT_STRING | SORT_FLAG_CASE);

// --------------------------------------------------------------------------
// Write output
// --------------------------------------------------------------------------

$outputDir = dirname($outputFile);
if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true)) {
    fwrite(STDERR, "Error: cannot create output directory: {$outputDir}" . PHP_EOL);
    exit(1);
}

$json = json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (file_put_contents($outputFile, $json) === false) {
    fwrite(STDERR, "Error: cannot write output file: {$outputFile}" . PHP_EOL);
    exit(1);
}

// --------------------------------------------------------------------------
// Summary
// --------------------------------------------------------------------------

echo PHP_EOL;
echo "Done." . PHP_EOL;
echo "  Files scanned      : {$stats['files_scanned']}" . PHP_EOL;
echo "  Files with strings : {$stats['files_with_strings']}" . PHP_EOL;
echo "  Unique strings     : " . count($strings) . PHP_EOL;
echo "  Output             : {$outputFile}" . PHP_EOL;
