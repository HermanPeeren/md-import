<?php

/**
 * Install the package this repository just built into a local Joomla.
 *
 *   php tools/install-local.php                 # into ./joomla
 *   php tools/install-local.php ../some/site
 *
 * Through Joomla's own CLI, which wants no login and no browser. The package is
 * found from the manifest's version rather than by name, because a hard-coded
 * file name keeps working until the version changes and then installs an old
 * build without saying so.
 */

declare(strict_types=1);

$root = \dirname(__DIR__);
$site = $argv[1] ?? $root . '/joomla';

$xml = simplexml_load_file($root . '/src/pkg_mdimport.xml');

if ($xml === false) {
    fwrite(STDERR, "Cannot read the manifest.\n");
    exit(1);
}

$package = \sprintf('%s/build/pkg_mdimport-%s.zip', $root, trim((string) $xml->version));

if (!is_file($package)) {
    fwrite(STDERR, "No package at {$package}. Run `composer build` first.\n");
    exit(1);
}

$cli = $site . '/cli/joomla.php';

if (!is_file($cli)) {
    fwrite(STDERR, "No Joomla CLI at {$cli}.\n");
    exit(1);
}

passthru(
    \sprintf('php %s extension:install --path=%s', escapeshellarg($cli), escapeshellarg($package)),
    $status
);

// The CLI unpacks the package beside it, into build/install_<id>/, and leaves
// it there.
foreach (glob($root . '/build/install_*', GLOB_ONLYDIR) ?: [] as $unpacked) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($unpacked, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($unpacked);
}

exit($status);
