<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Mdimport\Administrator\Source;

\defined('_JEXEC') or die;

/**
 * An unpacked Jdocmanual data repository, read the way Jdocmanual reads it.
 *
 * The layout, as in ceford/cefjdemos-data-jdm-help-en:
 *
 *   menu.json      section => [file.md => title], or for "menu-items" one level
 *                  deeper: section => subsection => [file.md => title]
 *   folders.json   section path with `/` written as `-` => title
 *   articles/      <section>[/<subsection>]/<slug>.md
 *   images/        <section>/<slug>/<image>, referenced from the Markdown as
 *                  `../../../en/images/<section>/<slug>/<image>`
 *
 * menu.json is the table of contents, so it decides what is a page, what it is
 * called and in which order it comes. A Markdown file that menu.json does not
 * list is still a page - it is imported - and is put at the end of the section
 * its folder says it belongs to.
 *
 * Nothing here touches Joomla, so all of it is unit-tested against a fixture.
 */
final class HelpRepository
{
    /** @var Section[]|null Top-level sections, in order. */
    private ?array $sections = null;

    /** @var array<string, Section> Every section by path. */
    private array $byPath = [];

    public function __construct(private readonly string $root)
    {
        if (!is_dir($root . '/articles')) {
            throw new \RuntimeException('Not a Jdocmanual data repository: there is no articles/ folder in ' . $root);
        }
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * Top-level sections in menu order, each with its pages and subsections.
     *
     * @return Section[]
     */
    public function sections(): array
    {
        if ($this->sections === null) {
            $this->load();
        }

        return $this->sections;
    }

    /**
     * Every section, parents before their children.
     *
     * @return Section[]
     */
    public function allSections(): array
    {
        $all  = [];
        $walk = static function (array $sections) use (&$walk, &$all): void {
            foreach ($sections as $section) {
                $all[] = $section;
                $walk($section->children);
            }
        };

        $walk($this->sections());

        return $all;
    }

    /**
     * Every page, in menu order.
     *
     * @return Page[]
     */
    public function pages(): array
    {
        $pages = [];

        foreach ($this->allSections() as $section) {
            foreach ($section->pages as $page) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    /**
     * Every image file, as a path relative to `images/`.
     *
     * @return string[]
     */
    public function images(): array
    {
        $directory = $this->root . '/images';

        if (!is_dir($directory)) {
            return [];
        }

        $images   = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || !self::isImage($file->getFilename())) {
                continue;
            }

            $images[] = str_replace('\\', '/', substr($file->getPathname(), \strlen($directory) + 1));
        }

        sort($images, SORT_STRING);

        return $images;
    }

    /**
     * The Markdown of one page.
     */
    public function read(Page $page): string
    {
        $contents = file_get_contents($this->root . '/' . $page->file);

        if ($contents === false) {
            throw new \RuntimeException('Cannot read ' . $page->file);
        }

        return $contents;
    }

    public static function isImage(string $filename): bool
    {
        return (bool) preg_match('/\.(png|jpe?g|gif|svg|webp)$/i', $filename);
    }

    private function load(): void
    {
        $menu    = $this->json('menu.json');
        $folders = $this->json('folders.json');

        $this->sections = [];
        $this->byPath   = [];
        $listed         = [];
        $ordering       = 0;

        foreach ($menu as $key => $entries) {
            if (!\is_array($entries)) {
                continue;
            }

            $this->sections[] = $this->section((string) $key, $entries, $folders, $ordering++, $listed);
        }

        $this->addUnlisted($listed, $folders);
    }

    /**
     * Build one section from its menu.json entry.
     *
     * @param   array<string, mixed>   $entries  file.md => title, or subsection => entries
     * @param   array<string, mixed>   $folders  folders.json
     * @param   array<string, bool>    $listed   Files seen so far, by repository path
     */
    private function section(string $path, array $entries, array $folders, int $ordering, array &$listed): Section
    {
        $section               = new Section($path, $this->folderTitle($path, $folders), $ordering);
        $this->byPath[$path]   = $section;
        $pageOrdering          = 0;
        $childOrdering         = 0;

        foreach ($entries as $name => $value) {
            $name = (string) $name;

            if (\is_array($value)) {
                $section->children[] = $this->section($path . '/' . $name, $value, $folders, $childOrdering++, $listed);

                continue;
            }

            if (!str_ends_with($name, '.md')) {
                continue;
            }

            $file = 'articles/' . $path . '/' . $name;

            if (!is_file($this->root . '/' . $file)) {
                // menu.json lists a page that is not there. Jdocmanual shows a
                // dead entry for it; an import has nothing to make an article of.
                continue;
            }

            $listed[$file]     = true;
            $section->pages[] = new Page(substr($name, 0, -3), (string) $value, $path, $file, $pageOrdering++);
        }

        return $section;
    }

    /**
     * Markdown files that menu.json does not mention, appended to their folder's
     * section - which is created when menu.json has none for it.
     *
     * @param   array<string, bool>   $listed
     * @param   array<string, mixed>  $folders
     */
    private function addUnlisted(array $listed, array $folders): void
    {
        $directory = $this->root . '/articles';
        $found     = [];
        $iterator  = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }

            $relative = 'articles/' . str_replace('\\', '/', substr($file->getPathname(), \strlen($directory) + 1));

            if (!isset($listed[$relative])) {
                $found[] = $relative;
            }
        }

        sort($found, SORT_STRING);

        foreach ($found as $relative) {
            $path = \dirname(substr($relative, \strlen('articles/')));

            if ($path === '.' || $path === '') {
                // A page directly under articles/ belongs to no section.
                continue;
            }

            $section            = $this->ensureSection($path, $folders);
            $slug               = basename($relative, '.md');
            $section->pages[] = new Page($slug, $this->pageTitle($relative, $slug), $path, $relative, \count($section->pages));
        }
    }

    /**
     * @param   array<string, mixed>  $folders
     */
    private function ensureSection(string $path, array $folders): Section
    {
        if (isset($this->byPath[$path])) {
            return $this->byPath[$path];
        }

        $parentPath = str_contains($path, '/') ? substr($path, 0, (int) strrpos($path, '/')) : '';

        if ($parentPath === '') {
            $section            = new Section($path, $this->folderTitle($path, $folders), \count($this->sections));
            $this->sections[] = $section;
        } else {
            $parent              = $this->ensureSection($parentPath, $folders);
            $section             = new Section($path, $this->folderTitle($path, $folders), \count($parent->children));
            $parent->children[] = $section;
        }

        $this->byPath[$path] = $section;

        return $section;
    }

    /**
     * @param   array<string, mixed>  $folders
     */
    private function folderTitle(string $path, array $folders): string
    {
        $key = str_replace('/', '-', $path);

        if (isset($folders[$key]) && \is_string($folders[$key])) {
            return $folders[$key];
        }

        return self::humanise(basename($path));
    }

    /**
     * The title a page not in menu.json gets: from the comment header the
     * Markdown opens with, or else from its slug.
     */
    private function pageTitle(string $relative, string $slug): string
    {
        $markdown = (string) file_get_contents($this->root . '/' . $relative);
        $title    = PageParser::parse($markdown)->metadata['title'] ?? '';

        return \is_string($title) && trim($title) !== '' ? trim($title) : self::humanise($slug);
    }

    public static function humanise(string $slug): string
    {
        return ucfirst(str_replace(['-', '_'], ' ', $slug));
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $name): array
    {
        $file = $this->root . '/' . $name;

        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return \is_array($decoded) ? $decoded : [];
    }
}
