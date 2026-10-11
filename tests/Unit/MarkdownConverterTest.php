<?php

declare(strict_types=1);

namespace Yepr\Component\Mdimport\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Mdimport\Administrator\Markdown\ConversionOptions;
use Yepr\Component\Mdimport\Administrator\Markdown\MarkdownConverter;
use Yepr\Component\Mdimport\Administrator\Markdown\UrlResolver;
use Yepr\Component\Mdimport\Administrator\Source\HelpRepository;
use Yepr\Component\Mdimport\Administrator\Source\PageParser;

/**
 * Markdown to HTML through the CommonMark library this package ships, with the
 * links and images of the manual rewritten for the site they are imported into.
 */
final class MarkdownConverterTest extends TestCase
{
    private function resolver(): UrlResolver
    {
        $articles = ['articles' => 'index.php?option=com_content&view=article&id=1&catid=9'];

        return new UrlResolver(
            static fn (string $slug): ?string => $articles[$slug] ?? null,
            'images/help',
            ['user' => '/user-guide/', 'help' => '/help-pages-references/'],
            'https://jdocmanual.org/en/'
        );
    }

    private function fixturePage(): string
    {
        $repository = new HelpRepository(__DIR__ . '/../Fixtures/help-repo');

        return PageParser::parse($repository->read($repository->pages()[1]))->markdown;
    }

    public function testGitHubFlavouredTablesAreConverted(): void
    {
        $html = (new MarkdownConverter(new ConversionOptions()))->convert($this->fixturePage());

        self::assertStringContainsString('<table>', $html);
        self::assertStringContainsString('<h2>Description</h2>', $html);
    }

    public function testPlainCommonMarkLeavesTablesAlone(): void
    {
        $html = (new MarkdownConverter(new ConversionOptions(ConversionOptions::FLAVOR_COMMONMARK)))->convert($this->fixturePage());

        self::assertStringNotContainsString('<table>', $html);
    }

    public function testRawHtmlFollowsTheHtmlInputOption(): void
    {
        $allowed  = (new MarkdownConverter(new ConversionOptions(htmlInput: 'allow')))->convert($this->fixturePage());
        $stripped = (new MarkdownConverter(new ConversionOptions(htmlInput: 'strip')))->convert($this->fixturePage());

        self::assertStringContainsString('<div class="alert alert-warning">', $allowed);
        self::assertStringNotContainsString('<div class="alert', $stripped);
    }

    public function testAHelpLinkGoesToTheImportedArticleKeepingItsFragment(): void
    {
        $html = (new MarkdownConverter(new ConversionOptions()))->convert($this->fixturePage(), $this->resolver());

        self::assertStringContainsString('href="index.php?option=com_content&amp;view=article&amp;id=1&amp;catid=9#column-headers"', $html);
    }

    public function testALinkToTheUserManualGoesToTheUserGuide(): void
    {
        $html = (new MarkdownConverter(new ConversionOptions()))->convert($this->fixturePage(), $this->resolver());

        self::assertStringContainsString('href="/user-guide/articles/adding-an-image-to-an-article"', $html);
    }

    public function testALinkToAPageThatWasNotImportedGoesToThePublishedHelpPages(): void
    {
        $html = (new MarkdownConverter(new ConversionOptions()))->convert($this->fixturePage(), $this->resolver());

        self::assertStringContainsString('href="/help-pages-references/nowhere/missing-page"', $html);
    }

    public function testAnImageGoesToTheImportedImagesFolder(): void
    {
        $html = (new MarkdownConverter(new ConversionOptions()))->convert($this->fixturePage(), $this->resolver());

        self::assertStringContainsString('src="images/help/menu-items/articles/single-article/01-details-tab.png"', $html);
    }

    public function testWithoutAResolverNothingIsRewritten(): void
    {
        $html = (new MarkdownConverter(new ConversionOptions()))->convert($this->fixturePage());

        self::assertStringContainsString('src="../../../../en/images/', $html);
    }

    public function testOptionalExtensionsCanBeSwitchedOn(): void
    {
        $options = ConversionOptions::fromParams([
            'md_flavor'     => 'gfm',
            'md_extensions' => ['table_of_contents', 'footnote', 'no_such_extension'],
        ]);

        self::assertSame(['footnote', 'table_of_contents'], $options->extensions);

        $html = (new MarkdownConverter($options))->convert("## One\n\nText[^1]\n\n## Two\n\n[^1]: A note\n");

        self::assertStringContainsString('table-of-contents', $html);
        self::assertStringContainsString('footnote', $html);
    }

    public function testUnknownOptionValuesFallBackToSafeDefaults(): void
    {
        $options = ConversionOptions::fromParams(['md_flavor' => 'markdown-extra', 'md_html_input' => 'execute']);

        self::assertSame(ConversionOptions::FLAVOR_GFM, $options->flavor);
        self::assertSame('allow', $options->htmlInput);
        self::assertFalse($options->allowUnsafeLinks);
    }
}
