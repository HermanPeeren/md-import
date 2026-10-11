<?php

declare(strict_types=1);

namespace Yepr\Component\Mdimport\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Mdimport\Administrator\Import\ImportSettings;

/**
 * The options as the importer reads them: defaults that work on a fresh site,
 * and nothing in them that can reach outside the images folder or GitHub.
 */
final class ImportSettingsTest extends TestCase
{
    public function testTheDefaultsImportTheEnglishHelp(): void
    {
        $settings = ImportSettings::fromParams([]);

        self::assertSame('https://codeload.github.com/ceford/cefjdemos-data-jdm-help-en/zip/2.0', $settings->downloadUrl());
        self::assertSame('images/jdm-help', $settings->imageBase());
        self::assertSame(ImportSettings::SECTION_HEADING, $settings->sectionMenuType);
        self::assertTrue($settings->sectionCategories);
        self::assertTrue($settings->updateExisting);
        self::assertTrue($settings->attribution);
        self::assertSame(0, $settings->parentMenuItem);
    }

    public function testTheImagesFolderIsTheChosenFolderPlusTheSubfolder(): void
    {
        self::assertSame('images/manuals/help', ImportSettings::fromParams(['images_folder' => 'manuals', 'images_subfolder' => 'help'])->imageBase());
        self::assertSame('images/manuals', ImportSettings::fromParams(['images_folder' => 'manuals', 'images_subfolder' => ''])->imageBase());
        self::assertSame('images', ImportSettings::fromParams(['images_folder' => '', 'images_subfolder' => ''])->imageBase());
    }

    public function testTheImagesFolderCannotClimbOut(): void
    {
        $settings = ImportSettings::fromParams(['images_folder' => '../../administrator', 'images_subfolder' => '/../x y/']);

        self::assertSame('images/administrator/x-y', $settings->imageBase());
    }

    public function testTheSourceCannotBePointedAnywhereButGitHub(): void
    {
        $settings = ImportSettings::fromParams([
            'source_owner'      => 'evil.example/x?',
            'source_repository' => 'repo/../../',
            'source_branch'     => '../main',
        ]);

        self::assertStringStartsWith('https://codeload.github.com/ceford/cefjdemos-data-jdm-help-en/zip/', $settings->downloadUrl());
        self::assertSame('2.0', $settings->branch);
    }

    public function testAnotherLanguageIsAnotherRepository(): void
    {
        $settings = ImportSettings::fromParams(['source_repository' => 'cefjdemos-data-jdm-help-nl', 'source_branch' => 'main']);

        self::assertSame('https://codeload.github.com/ceford/cefjdemos-data-jdm-help-nl/zip/main', $settings->downloadUrl());
    }

    public function testCategoryMenuItemsNeedSectionCategories(): void
    {
        $settings = ImportSettings::fromParams(['section_menu_type' => 'category_blog', 'section_categories' => 0]);

        self::assertSame(ImportSettings::SECTION_HEADING, $settings->sectionMenuType);

        $settings = ImportSettings::fromParams(['section_menu_type' => 'category_blog', 'section_categories' => 1]);

        self::assertSame(ImportSettings::SECTION_CATEGORY_BLOG, $settings->sectionMenuType);
    }

    public function testSkipIsTheOnlyOtherWayToTreatExistingPages(): void
    {
        self::assertFalse(ImportSettings::fromParams(['on_existing' => 'skip'])->updateExisting);
        self::assertTrue(ImportSettings::fromParams(['on_existing' => 'anything'])->updateExisting);
    }

    public function testTheSourceLinePointsAtThePublishedPage(): void
    {
        self::assertSame(
            'https://guide.joomla.org/help-pages-references/articles/articles',
            ImportSettings::fromParams([])->sourceUrl('articles', 'articles')
        );
        self::assertSame(
            'https://guide.joomla.org/help-pages-references/menu-items/articles/single-article',
            ImportSettings::fromParams([])->sourceUrl('menu-items/articles', 'single-article')
        );
    }

    public function testLinksIntoOtherManualsGoToTheGuideByDefault(): void
    {
        self::assertSame(['user' => '/user-guide/', 'help' => '/help-pages-references/'], ImportSettings::fromParams([])->manualBases());
        self::assertSame('', ImportSettings::fromParams(['user_guide_url' => ''])->manualBases()['user']);
    }
}
