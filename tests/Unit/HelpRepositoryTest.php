<?php

declare(strict_types=1);

namespace Yepr\Component\Mdimport\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Mdimport\Administrator\Source\HelpRepository;
use Yepr\Component\Mdimport\Administrator\Source\Page;
use Yepr\Component\Mdimport\Administrator\Source\PageParser;

/**
 * Reading a Jdocmanual data repository: what is a page, what it is called and
 * where it goes. The fixture is a cut-down copy of the real one, with the
 * three oddities the real one has - a nested section, a page menu.json
 * forgets, and an entry menu.json lists that is not on disk.
 */
final class HelpRepositoryTest extends TestCase
{
    private HelpRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new HelpRepository(__DIR__ . '/../Fixtures/help-repo');
    }

    public function testSectionsComeInMenuOrderWithTheirTitles(): void
    {
        $sections = $this->repository->sections();

        self::assertSame(['articles', 'menu-items', 'glossary'], array_map(static fn ($s) => $s->path, $sections));
        self::assertSame(['Articles', 'Menu Items', 'Glossary'], array_map(static fn ($s) => $s->title, $sections));
    }

    public function testANestedSectionIsAChildWithItsOwnTitle(): void
    {
        $menuItems = $this->repository->sections()[1];

        self::assertCount(1, $menuItems->children);
        self::assertSame('menu-items/articles', $menuItems->children[0]->path);
        self::assertSame('Menu Items: Articles', $menuItems->children[0]->title);
        self::assertSame('articles', $menuItems->children[0]->alias());
        self::assertSame('menu-items', $menuItems->children[0]->parentPath());
    }

    public function testPagesAreListedInMenuOrderWithMenuTitles(): void
    {
        $pages = $this->repository->pages();

        self::assertSame(
            ['articles', 'articles-edit', 'single-article', 'glossary'],
            array_map(static fn (Page $p) => $p->slug, $pages)
        );
        self::assertSame('Articles: Edit', $pages[1]->title);
        self::assertSame('articles/menu-items/articles/single-article.md', $pages[2]->file);
        self::assertSame('menu-items/articles', $pages[2]->sectionPath);
    }

    public function testAPageMenuJsonListsButDoesNotHaveIsSkipped(): void
    {
        $slugs = array_map(static fn (Page $p) => $p->slug, $this->repository->pages());

        self::assertNotContains('listed-but-missing', $slugs);
    }

    public function testAnUnlistedPageIsKeptAndTitledFromItsHeader(): void
    {
        $glossary = $this->repository->sections()[2];

        self::assertSame('Glossary of Terms', $glossary->pages[0]->title);
    }

    public function testImagesAreListedRelativeToTheImagesFolder(): void
    {
        self::assertSame(
            ['articles/articles/01-articles-list.png', 'menu-items/articles/single-article/01-details-tab.png'],
            $this->repository->images()
        );
    }

    public function testSlugsAreUniqueInTheFixture(): void
    {
        $slugs = array_map(static fn (Page $p) => $p->slug, $this->repository->pages());

        self::assertSame($slugs, array_unique($slugs));
    }

    public function testAFolderWithoutArticlesIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);

        new HelpRepository(__DIR__);
    }

    public function testTheMetadataHeaderIsTakenOff(): void
    {
        $parsed = PageParser::parse($this->repository->read($this->repository->pages()[1]));

        self::assertSame('Articles: Edit', $parsed->string('title'));
        self::assertSame('How to edit an article.', $parsed->string('description'));
        self::assertStringStartsWith('## Description', $parsed->markdown);
        self::assertStringNotContainsString('<!--', $parsed->markdown);
    }

    public function testAPageWithoutAHeaderIsLeftWhole(): void
    {
        $parsed = PageParser::parse("<!-- just a comment -->\n# Title\n");

        self::assertSame([], $parsed->metadata);
        self::assertStringContainsString('<!-- just a comment -->', $parsed->markdown);
    }
}
