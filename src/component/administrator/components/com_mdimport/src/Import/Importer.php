<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Mdimport\Administrator\Import;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Yepr\Component\Mdimport\Administrator\Markdown\MarkdownConverter;
use Yepr\Component\Mdimport\Administrator\Markdown\UrlResolver;
use Yepr\Component\Mdimport\Administrator\Source\HelpRepository;
use Yepr\Component\Mdimport\Administrator\Source\Page;
use Yepr\Component\Mdimport\Administrator\Source\PageParser;
use Yepr\Component\Mdimport\Administrator\Source\Section;

/**
 * The import, as a sequence of steps the browser asks for one batch at a time.
 *
 *   download    fetch the repository archive and unpack it
 *   images      copy the screenshots into the images folder
 *   categories  a category for the import, and one per section
 *   articles    make sure every page has an article, so every link has a target
 *   content     convert each page and write it into its article
 *   menu        a heading (or category item) per section, an item per page
 *   cleanup     remove the download
 *
 * Batches, because a site's max_execution_time is often 30 seconds and the
 * English manual is some 280 pages and 900 images. Articles before content,
 * because a page links to pages that come after it, and those need an id to
 * be linked to.
 *
 * @phpstan-type StepResult array{step: string, offset: int, total: int, done: bool, next: ?string, message: string}
 */
final class Importer
{
    public const STEPS = ['download', 'images', 'categories', 'articles', 'content', 'menu', 'cleanup'];

    private const BATCH = [
        'images'   => 100,
        'articles' => 40,
        'content'  => 20,
        'menu'     => 40,
    ];

    private ?HelpRepository $repository = null;

    public function __construct(
        private readonly ImportSettings $settings,
        private readonly ItemMap $map,
        private readonly ContentWriter $writer,
        private readonly Downloader $downloader,
        private readonly MarkdownConverter $converter,
        private readonly string $workDirectory,
        private readonly string $siteRoot
    ) {
    }

    /**
     * Run one batch of one step.
     *
     * @return StepResult
     */
    public function run(string $step, int $offset): array
    {
        if (!\in_array($step, self::STEPS, true)) {
            throw new \InvalidArgumentException('Unknown import step: ' . $step);
        }

        return match ($step) {
            'download'   => $this->download(),
            'images'     => $this->images($offset),
            'categories' => $this->categories(),
            'articles'   => $this->articles($offset),
            'content'    => $this->content($offset),
            'menu'       => $this->menu($offset),
            'cleanup'    => $this->cleanup(),
        };
    }

    /**
     * What is wrong with the options before an import can start, if anything.
     *
     * @return string[]
     */
    public function problems(): array
    {
        $problems = [];

        if ($this->settings->parentMenuItem <= 0) {
            $problems[] = Text::_('COM_MDIMPORT_PROBLEM_NO_PARENT_MENU_ITEM');
        } elseif ($this->writer->menuTypeOf($this->settings->parentMenuItem) === null) {
            $problems[] = Text::_('COM_MDIMPORT_PROBLEM_PARENT_MENU_ITEM_GONE');
        }

        return $problems;
    }

    /**
     * @return StepResult
     */
    private function download(): array
    {
        $problems = $this->problems();

        if ($problems !== []) {
            throw new \RuntimeException(implode(' ', $problems));
        }

        $this->removeDirectory($this->workDirectory);

        $archive = $this->workDirectory . '/source.zip';

        $this->downloader->download($this->settings->downloadUrl(), $archive);

        $root = $this->downloader->extract($archive, $this->workDirectory . '/source');

        @unlink($archive);

        file_put_contents($this->workDirectory . '/state.json', json_encode(['root' => $root]));

        $this->repository = null;
        $repository       = $this->repository();

        return $this->result('download', 1, 1, Text::sprintf(
            'COM_MDIMPORT_STEP_DOWNLOAD_DONE',
            \count($repository->pages()),
            \count($repository->allSections()),
            \count($repository->images())
        ));
    }

    /**
     * @return StepResult
     */
    private function images(int $offset): array
    {
        $images = $this->repository()->images();
        $total  = \count($images);
        $source = $this->repository()->root() . '/images/';
        $target = $this->siteRoot . '/' . $this->settings->imageBase() . '/';

        foreach (\array_slice($images, $offset, self::BATCH['images']) as $image) {
            $to = $target . $image;

            if (!is_dir(\dirname($to)) && !mkdir(\dirname($to), 0755, true) && !is_dir(\dirname($to))) {
                throw new \RuntimeException('Cannot create ' . \dirname($to));
            }

            if (!copy($source . $image, $to)) {
                throw new \RuntimeException('Cannot copy ' . $image . ' to ' . $to);
            }
        }

        $next = min($total, $offset + self::BATCH['images']);

        return $this->result('images', $next, $total, Text::sprintf('COM_MDIMPORT_STEP_IMAGES_PROGRESS', $next, $total));
    }

    /**
     * @return StepResult
     */
    private function categories(): array
    {
        $root = $this->rootCategory();

        if ($this->settings->sectionCategories) {
            foreach ($this->repository()->allSections() as $section) {
                $parentPath = $section->parentPath();
                $parentId   = $parentPath === '' ? $root : (int) ($this->map->get('section:' . $parentPath)->category_id ?? $root);
                $existing   = $this->map->get($section->key());
                $id         = (int) ($existing->category_id ?? 0);

                if ($id > 0 && $this->map->exists('#__categories', $id) && !$this->settings->updateExisting) {
                    continue;
                }

                if ($id <= 0 || !$this->map->exists('#__categories', $id)) {
                    $id = $this->writer->findCategory($parentId, $section->alias());
                }

                $id = $this->writer->saveCategory($id, [
                    'title'     => $section->title,
                    'alias'     => $section->alias(),
                    'parent_id' => $parentId,
                    'published' => 1,
                    'access'    => $this->settings->access,
                    'language'  => $this->settings->language,
                ]);

                $this->map->save($section->key(), 'section', $section->alias(), ['category_id' => $id]);
            }
        }

        $count = $this->settings->sectionCategories ? \count($this->repository()->allSections()) : 0;

        return $this->result('categories', 1, 1, Text::sprintf('COM_MDIMPORT_STEP_CATEGORIES_DONE', $count + 1));
    }

    /**
     * The category everything goes into: the one chosen in the options, or else
     * a "Help" category at the top, made once and remembered.
     */
    private function rootCategory(): int
    {
        if ($this->settings->parentCategory > 0) {
            return $this->settings->parentCategory;
        }

        $id = (int) ($this->map->get('category:root')->category_id ?? 0);

        if ($id > 0 && $this->map->exists('#__categories', $id)) {
            return $id;
        }

        $id = $this->writer->findCategory(1, 'help')
            ?: $this->writer->saveCategory(0, [
                'title'     => Text::_('COM_MDIMPORT_HELP_CATEGORY_TITLE'),
                'alias'     => 'help',
                'parent_id' => 1,
                'published' => 1,
                'access'    => $this->settings->access,
                'language'  => $this->settings->language,
            ]);

        $this->map->save('category:root', 'category', 'help', ['category_id' => $id]);

        return $id;
    }

    private function categoryFor(Page $page): int
    {
        if ($this->settings->sectionCategories) {
            $id = (int) ($this->map->get('section:' . $page->sectionPath)->category_id ?? 0);

            if ($id > 0) {
                return $id;
            }
        }

        return $this->rootCategory();
    }

    /**
     * @return StepResult
     */
    private function articles(int $offset): array
    {
        $pages = $this->repository()->pages();
        $total = \count($pages);

        foreach (\array_slice($pages, $offset, self::BATCH['articles']) as $page) {
            $existing = $this->map->get($page->key());
            $id       = (int) ($existing->article_id ?? 0);
            $catid    = $this->categoryFor($page);

            if ($id > 0 && $this->map->exists('#__content', $id)) {
                continue;
            }

            // An article with this alias in this category - from an import whose
            // record was lost, or made by hand - is adopted, not duplicated.
            $id = $this->writer->findArticle($catid, $page->slug)
                ?: $this->writer->saveArticle(0, [
                    'title'       => $page->title,
                    'alias'       => $page->slug,
                    'catid'       => $catid,
                    'articletext' => '',
                    'state'       => $this->settings->published,
                    'access'      => $this->settings->access,
                    'language'    => $this->settings->language,
                ]);

            // A new article has no content yet, so whatever hash was stored no
            // longer describes it.
            $this->map->save($page->key(), 'page', $page->slug, ['article_id' => $id, 'category_id' => $catid, 'hash' => '']);
        }

        $next = min($total, $offset + self::BATCH['articles']);

        return $this->result('articles', $next, $total, Text::sprintf('COM_MDIMPORT_STEP_ARTICLES_PROGRESS', $next, $total));
    }

    /**
     * @return StepResult
     */
    private function content(int $offset): array
    {
        $pages     = $this->repository()->pages();
        $total     = \count($pages);
        $bySlug    = $this->map->pagesBySlug();
        $resolver  = new UrlResolver(
            static function (string $slug) use ($bySlug): ?string {
                if (!isset($bySlug[$slug])) {
                    return null;
                }

                return \sprintf(
                    'index.php?option=com_content&view=article&id=%d&catid=%d',
                    (int) $bySlug[$slug]->article_id,
                    (int) $bySlug[$slug]->category_id
                );
            },
            $this->settings->imageBase(),
            $this->settings->manualBases(),
            $this->settings->manualBase
        );

        foreach (\array_slice($pages, $offset, self::BATCH['content']) as $page) {
            $row = $this->map->get($page->key());

            if ($row === null || (int) $row->article_id <= 0) {
                continue;
            }

            // Skip means: a page whose article already has content is left alone.
            if (!$this->settings->updateExisting && $row->hash !== '') {
                continue;
            }

            $parsed = PageParser::parse($this->repository()->read($page));
            $html   = $this->converter->convert($parsed->markdown, $resolver);

            if ($this->settings->attribution) {
                $html .= "\n" . '<p class="mdimport-attribution"><small>'
                    . Text::sprintf('COM_MDIMPORT_ATTRIBUTION', htmlspecialchars($this->settings->sourceUrl($page->sectionPath, $page->slug), ENT_QUOTES))
                    . '</small></p>' . "\n";
            }

            $catid = $this->categoryFor($page);
            $data  = [
                'title'       => $page->title,
                'alias'       => $page->slug,
                'catid'       => $catid,
                'articletext' => $html,
                'metadesc'    => $parsed->string('description'),
                'access'      => $this->settings->access,
                'language'    => $this->settings->language,
            ];

            $hash = sha1(serialize($data));

            // Unchanged since the last import: saving again would only add a
            // version to the article's history that differs in nothing.
            if ($hash === $row->hash && $this->map->exists('#__content', (int) $row->article_id)) {
                continue;
            }

            $id = $this->writer->saveArticle((int) $row->article_id, $data);

            $this->map->save($page->key(), 'page', $page->slug, ['article_id' => $id, 'category_id' => $catid, 'hash' => $hash]);
        }

        $next = min($total, $offset + self::BATCH['content']);

        return $this->result('content', $next, $total, Text::sprintf('COM_MDIMPORT_STEP_CONTENT_PROGRESS', $next, $total));
    }

    /**
     * Sections first, parents before children, then pages - so that every
     * item's parent exists before it is made.
     */
    /**
     * @return StepResult
     */
    private function menu(int $offset): array
    {
        $sections = $this->repository()->allSections();
        $pages    = $this->repository()->pages();
        $items    = array_merge($sections, $pages);
        $total    = \count($items);
        $menutype = $this->writer->menuTypeOf($this->settings->parentMenuItem);

        if ($menutype === null) {
            throw new \RuntimeException(Text::_('COM_MDIMPORT_PROBLEM_PARENT_MENU_ITEM_GONE'));
        }

        foreach (\array_slice($items, $offset, self::BATCH['menu']) as $item) {
            if ($item instanceof Section) {
                $this->sectionMenuItem($item, $menutype);
            } else {
                $this->pageMenuItem($item, $menutype);
            }
        }

        $next = min($total, $offset + self::BATCH['menu']);

        return $this->result('menu', $next, $total, Text::sprintf('COM_MDIMPORT_STEP_MENU_PROGRESS', $next, $total));
    }

    private function sectionMenuItem(Section $section, string $menutype): void
    {
        $row = $this->map->get($section->key());
        $id  = (int) ($row->menu_id ?? 0);

        if ($id > 0 && $this->map->exists('#__menu', $id) && !$this->settings->updateExisting) {
            return;
        }

        if ($id > 0 && !$this->map->exists('#__menu', $id)) {
            $id = 0;
        }

        $parentPath = $section->parentPath();
        $parentId   = $parentPath === ''
            ? $this->settings->parentMenuItem
            : (int) ($this->map->get('section:' . $parentPath)->menu_id ?? $this->settings->parentMenuItem);

        $data = [
            'menutype'  => $menutype,
            'title'     => $section->title,
            'alias'     => $section->alias(),
            'parent_id' => $parentId,
            'published' => $this->settings->published,
            'access'    => $this->settings->access,
            'language'  => $this->settings->language,
        ];

        $categoryId = (int) ($row->category_id ?? 0);

        if ($this->settings->sectionMenuType !== ImportSettings::SECTION_HEADING && $categoryId > 0) {
            $data['type']         = 'component';
            $data['component_id'] = $this->writer->contentComponentId();
            $data['link']         = 'index.php?option=com_content&view=category'
                . ($this->settings->sectionMenuType === ImportSettings::SECTION_CATEGORY_BLOG ? '&layout=blog' : '')
                . '&id=' . $categoryId;
        } else {
            $data['type']         = 'heading';
            $data['component_id'] = 0;
            $data['link']         = '';
        }

        $id = $this->writer->saveMenuItem($id, $data);

        $this->map->save($section->key(), 'section', $section->alias(), ['menu_id' => $id]);
    }

    private function pageMenuItem(Page $page, string $menutype): void
    {
        $row = $this->map->get($page->key());

        if ($row === null || (int) $row->article_id <= 0) {
            return;
        }

        $id = (int) $row->menu_id;

        if ($id > 0 && $this->map->exists('#__menu', $id) && !$this->settings->updateExisting) {
            return;
        }

        if ($id > 0 && !$this->map->exists('#__menu', $id)) {
            $id = 0;
        }

        $parentId = (int) ($this->map->get('section:' . $page->sectionPath)->menu_id ?? 0) ?: $this->settings->parentMenuItem;

        $id = $this->writer->saveMenuItem($id, [
            'menutype'     => $menutype,
            'title'        => $page->title,
            'alias'        => $page->slug,
            'parent_id'    => $parentId,
            'type'         => 'component',
            'component_id' => $this->writer->contentComponentId(),
            'link'         => 'index.php?option=com_content&view=article&id=' . (int) $row->article_id,
            'published'    => $this->settings->published,
            'access'       => $this->settings->access,
            'language'     => $this->settings->language,
        ]);

        $this->map->save($page->key(), 'page', $page->slug, ['menu_id' => $id]);
    }

    /**
     * @return StepResult
     */
    private function cleanup(): array
    {
        $this->removeDirectory($this->workDirectory);

        return $this->result('cleanup', 1, 1, Text::_('COM_MDIMPORT_STEP_CLEANUP_DONE'));
    }

    private function repository(): HelpRepository
    {
        if ($this->repository === null) {
            $state = json_decode((string) @file_get_contents($this->workDirectory . '/state.json'), true);

            if (!\is_array($state) || !isset($state['root']) || !is_dir((string) $state['root'])) {
                throw new \RuntimeException(Text::_('COM_MDIMPORT_PROBLEM_NOT_DOWNLOADED'));
            }

            $this->repository = new HelpRepository((string) $state['root']);
        }

        return $this->repository;
    }

    /**
     * @return StepResult
     */
    private function result(string $step, int $offset, int $total, string $message): array
    {
        $done  = $offset >= $total;
        $index = (int) array_search($step, self::STEPS, true);

        return [
            'step'    => $step,
            'offset'  => $offset,
            'total'   => $total,
            'done'    => $done,
            'next'    => $done ? (self::STEPS[$index + 1] ?? null) : $step,
            'message' => $message,
        ];
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
