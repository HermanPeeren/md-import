<?php

declare(strict_types=1);

namespace Yepr\Component\Mdimport\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The things a release needs to agree on, checked before there is a release.
 *
 * Three manifests carry one version; the update server repeats it; the
 * component manifest names every folder the installer copies, and a folder it
 * forgets is silently not installed.
 */
final class ReleaseTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private static function version(string $manifest): string
    {
        $xml = simplexml_load_file(self::ROOT . '/' . $manifest);
        self::assertNotFalse($xml, $manifest);

        return trim((string) $xml->version);
    }

    public function testTheThreeManifestsCarryOneVersion(): void
    {
        $package = self::version('src/pkg_mdimport.xml');

        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $package);
        self::assertSame($package, self::version('src/component/mdimport.xml'));
        self::assertSame($package, self::version('src/library/yepr_commonmark/yepr_commonmark.xml'));
    }

    public function testTheUpdateServerOffersThisVersion(): void
    {
        $updates = simplexml_load_file(self::ROOT . '/updates.xml');
        self::assertNotFalse($updates);

        $version = self::version('src/pkg_mdimport.xml');

        self::assertSame($version, trim((string) $updates->update->version), 'Run php build/update-xml.php');
        self::assertSame('pkg_mdimport', trim((string) $updates->update->element));
        self::assertStringEndsWith("/v{$version}/pkg_mdimport-{$version}.zip", trim((string) $updates->update->downloads->downloadurl));
    }

    public function testThePackageListsWhatTheBuildPutsInIt(): void
    {
        $xml = simplexml_load_file(self::ROOT . '/src/pkg_mdimport.xml');
        self::assertNotFalse($xml);

        $files = [];

        foreach ($xml->files->file as $file) {
            $files[(string) $file['type']] = (string) $file;
        }

        // The library first: Joomla installs in this order, and the component
        // needs the library the moment it is opened.
        self::assertSame(['library' => 'lib_yepr_commonmark.zip', 'component' => 'com_mdimport.zip'], $files);
    }

    public function testTheComponentManifestNamesEveryFolderItShips(): void
    {
        $xml = simplexml_load_file(self::ROOT . '/src/component/mdimport.xml');
        self::assertNotFalse($xml);

        $base   = self::ROOT . '/src/component/administrator/components/com_mdimport';
        $listed = [];

        foreach ($xml->administration->files->children() as $child) {
            $listed[] = (string) $child;
        }

        $present = array_map('basename', array_merge(glob($base . '/*', GLOB_ONLYDIR) ?: [], glob($base . '/*.xml') ?: []));

        sort($listed);
        sort($present);

        self::assertSame($present, $listed);
    }

    public function testTheLibraryShipsItsAutoloader(): void
    {
        self::assertFileExists(self::ROOT . '/src/library/yepr_commonmark/autoload.php');
        self::assertFileExists(self::ROOT . '/src/library/yepr_commonmark/vendor/autoload.php', 'Run composer install at the repository root.');
    }
}
