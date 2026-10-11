<?php

declare(strict_types=1);

namespace Yepr\Component\Mdimport\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * This component's services are made in one place: its service provider.
 *
 * Until 0.1.2 the import model built the importer and everything it needs with
 * `new`. They are registered by `Service\Provider\Import` now, and the
 * component's MVC factory hands them to whatever declares an aware interface
 * for them - the way Exten-gen does it.
 *
 * A boundary nothing enforces erodes one convenient `new` at a time, so this
 * reads the source.
 */
final class ServiceConstructionTest extends TestCase
{
    private const COMPONENT = __DIR__ . '/../../src/component/administrator/components/com_mdimport';

    private const PROVIDER = 'src/Service/Provider/Import.php';

    /**
     * How each service is made, as it would appear in source.
     */
    private const CONSTRUCTIONS = [
        'ImportSettings::fromParams(',
        'new ItemMap(',
        'new ContentWriter(',
        'new Downloader(',
        'new MarkdownConverter(',
        'new Importer(',
    ];

    /**
     * @return array<string, string>  Path relative to the component => source.
     */
    private function sources(): array
    {
        $root    = realpath(self::COMPONENT);
        $sources = [];
        $tree    = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator((string) $root, \FilesystemIterator::SKIP_DOTS));

        foreach ($tree as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if (str_ends_with($path, '.php')) {
                $sources[substr($path, \strlen((string) $root) + 1)] = (string) file_get_contents($path);
            }
        }

        return $sources;
    }

    public function testTheServicesAreConstructedOnlyByTheProvider(): void
    {
        $found = [];

        foreach ($this->sources() as $path => $source) {
            if ($path === self::PROVIDER) {
                continue;
            }

            foreach (self::CONSTRUCTIONS as $construction) {
                // ImportSettings defines fromParams() itself; defining is not constructing.
                if (str_contains($source, $construction) && !str_ends_with($path, '/ImportSettings.php')) {
                    $found[] = $path . ': ' . $construction;
                }
            }
        }

        self::assertSame([], $found, 'register it in Service\\Provider\\Import and declare an aware interface instead');
    }

    public function testTheProviderConstructsEachOfThem(): void
    {
        // The other half: an empty provider would pass the test above.
        $provider = $this->sources()[self::PROVIDER] ?? '';

        foreach (self::CONSTRUCTIONS as $construction) {
            self::assertStringContainsString($construction, $provider);
        }
    }

    public function testTheMvcClassesConstructNoServicesAtAll(): void
    {
        $found = [];

        foreach ($this->sources() as $path => $source) {
            if (!preg_match('#^(src/(Controller|Model|View|Extension)/|tmpl/)#', $path)) {
                continue;
            }

            // Exceptions and Joomla's JSON response are values, not services.
            preg_match_all('/\bnew\s+(?!\\\\?\w*Exception\b|JsonResponse\b)[\\\\A-Za-z_]+/', $source, $matches);

            foreach ($matches[0] as $construction) {
                $found[] = $path . ': ' . $construction;
            }
        }

        self::assertSame([], $found);
    }

    public function testTheComponentRegistersItsOwnFactoryAndTheServices(): void
    {
        $provider = (string) file_get_contents(self::COMPONENT . '/services/provider.php');

        self::assertStringContainsString('use Yepr\\Component\\Mdimport\\Administrator\\Service\\Provider\\MVCFactory;', $provider);
        self::assertStringNotContainsString('use Joomla\\CMS\\Extension\\Service\\Provider\\MVCFactory;', $provider);
        self::assertStringContainsString('new Import()', $provider);
    }
}
