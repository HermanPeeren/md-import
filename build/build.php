<?php

/**
 * Assembles the installable package.
 *
 *   php build/build.php        -> build/pkg_mdimport-<version>.zip
 *
 * A Joomla package is a zip holding other zips:
 *
 *   pkg_mdimport.xml                    the package manifest
 *   script.php                          refuses a PHP or Joomla that is too old
 *   language/en-GB/pkg_mdimport.sys.ini
 *   packages/lib_yepr_commonmark.zip    league/commonmark and its dependencies
 *   packages/com_mdimport.zip           the component
 *
 * The library's vendor tree is installed by `composer install` at the root (a
 * post-install script) and is not in git. This refuses to build without it
 * rather than ship a library with nothing in it.
 *
 * The three manifests carry one version. It is edited in pkg_mdimport.xml and
 * the other two must agree; ReleaseTest says so too, earlier.
 */

declare(strict_types=1);

$root = \dirname(__DIR__);
$src  = $root . '/src';

$package   = manifest($src . '/pkg_mdimport.xml');
$component = manifest($src . '/component/mdimport.xml');
$library   = manifest($src . '/library/yepr_commonmark/yepr_commonmark.xml');
$version   = trim((string) $package->version);

foreach (['component' => $component, 'library' => $library] as $name => $xml) {
    if (trim((string) $xml->version) !== $version) {
        fwrite(STDERR, "The {$name} manifest says " . trim((string) $xml->version) . ", the package {$version}. Make them agree.\n");
        exit(1);
    }
}

if (!is_file($src . '/library/yepr_commonmark/vendor/league/commonmark/src/MarkdownConverter.php')) {
    fwrite(STDERR, "The CommonMark library has no vendor tree. Run `composer install` at the repository root.\n");
    exit(1);
}

$tmp = $root . '/build/tmp';

if (!is_dir($tmp) && !mkdir($tmp, 0755, true) && !is_dir($tmp)) {
    fwrite(STDERR, "Cannot create {$tmp}.\n");
    exit(1);
}

echo "Building pkg_mdimport {$version}\n";

$componentZip = $tmp . '/com_mdimport.zip';
$libraryZip   = $tmp . '/lib_yepr_commonmark.zip';

zipFolder($src . '/component', $componentZip);
zipFolder($src . '/library/yepr_commonmark', $libraryZip, [
    // What composer leaves that a site has no use for.
    'composer.lock',
    'vendor/bin/',
]);

$zipPath = $root . '/build/pkg_mdimport-' . $version . '.zip';

if (is_file($zipPath)) {
    unlink($zipPath);
}

$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    fwrite(STDERR, "Cannot create {$zipPath}.\n");
    exit(1);
}

$zip->addFile($src . '/pkg_mdimport.xml', 'pkg_mdimport.xml');
$zip->addFile($src . '/script.php', 'script.php');
$zip->addFile($src . '/language/en-GB/pkg_mdimport.sys.ini', 'language/en-GB/pkg_mdimport.sys.ini');
$zip->addFile($componentZip, 'packages/com_mdimport.zip');
$zip->addFile($libraryZip, 'packages/lib_yepr_commonmark.zip');
$zip->close();

unlink($componentZip);
unlink($libraryZip);

printf("\n%s\n  %s\n", basename($zipPath), formatSize((int) filesize($zipPath)));

/**
 * Zip a folder, with forward slashes always: a backslash in a zip entry becomes
 * part of a file name on Linux, and the extension then fails to load.
 *
 * @param   string[]  $skip  Entry prefixes to leave out
 */
function zipFolder(string $folder, string $target, array $skip = []): void
{
    if (is_file($target)) {
        unlink($target);
    }

    $zip = new ZipArchive();

    if ($zip->open($target, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
        fwrite(STDERR, "Cannot create {$target}.\n");
        exit(1);
    }

    $files    = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = $file->getPathname();
        }
    }

    sort($files, SORT_STRING);

    $count = 0;

    foreach ($files as $absolute) {
        $entry = str_replace('\\', '/', substr($absolute, \strlen($folder) + 1));

        foreach ($skip as $prefix) {
            if (str_starts_with($entry, $prefix) || str_contains($entry, '/' . $prefix)) {
                continue 2;
            }
        }

        $zip->addFile($absolute, $entry);
        $count++;
    }

    $zip->close();

    printf("  %-28s %5d files, %s\n", basename($target), $count, formatSize((int) filesize($target)));
}

function manifest(string $path): SimpleXMLElement
{
    $xml = simplexml_load_file($path);

    if ($xml === false) {
        fwrite(STDERR, "Cannot read the manifest at {$path}.\n");
        exit(1);
    }

    return $xml;
}

function formatSize(int $bytes): string
{
    return $bytes > 1048576
        ? number_format($bytes / 1048576, 1) . ' MB'
        : number_format($bytes / 1024, 1) . ' KB';
}
