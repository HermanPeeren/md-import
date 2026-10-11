<?php

declare(strict_types=1);

namespace Yepr\Component\Mdimport\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every language key the component uses is defined, and every .ini parses.
 *
 * Joomla shows an undefined key as itself, in capitals, and logs nothing. This
 * finds the literal ones in the source; the browser tests find the rest.
 */
final class LanguageStringsTest extends TestCase
{
    private const COMPONENT = __DIR__ . '/../../src/component';

    /**
     * @return array<string, string>
     */
    private static function strings(): array
    {
        $strings = [];

        foreach (glob(self::COMPONENT . '/administrator/components/com_mdimport/language/en-GB/*.ini') ?: [] as $ini) {
            $parsed = parse_ini_file($ini, false, INI_SCANNER_RAW);
            self::assertIsArray($parsed, basename($ini) . ' does not parse');
            $strings += $parsed;
        }

        return $strings;
    }

    public function testEveryKeyUsedIsDefined(): void
    {
        $strings  = self::strings();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::COMPONENT, \FilesystemIterator::SKIP_DOTS));
        $missing  = [];

        foreach ($iterator as $file) {
            if (!preg_match('/\.(php|xml|js)$/', $file->getFilename())) {
                continue;
            }

            preg_match_all('/\bCOM_MDIMPORT_[A-Z0-9_]+\b/', (string) file_get_contents($file->getPathname()), $matches);

            foreach ($matches[0] as $key) {
                // A prefix the code completes at run time, e.g. COM_MDIMPORT_STEP_ + the step.
                if (str_ends_with($key, '_')) {
                    continue;
                }

                if (!isset($strings[$key])) {
                    $missing[$key] = $file->getFilename();
                }
            }
        }

        self::assertSame([], $missing);
    }

    public function testEveryStepHasItsStrings(): void
    {
        $strings = self::strings();

        foreach (['DOWNLOAD', 'IMAGES', 'CATEGORIES', 'ARTICLES', 'CONTENT', 'MENU', 'CLEANUP'] as $step) {
            self::assertArrayHasKey('COM_MDIMPORT_STEP_' . $step, $strings);
        }
    }

    public function testTheSectionMenuTypesHaveTheirStrings(): void
    {
        $strings = self::strings();

        foreach (['HEADING', 'CATEGORY_LIST', 'CATEGORY_BLOG'] as $type) {
            self::assertArrayHasKey('COM_MDIMPORT_OPTION_SECTION_' . $type, $strings);
        }
    }
}
