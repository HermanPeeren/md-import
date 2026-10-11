<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Mdimport\Administrator\Import;

\defined('_JEXEC') or die;

use Yepr\Component\Mdimport\Administrator\Markdown\ConversionOptions;

/**
 * The component's options, read once and checked, as the importer uses them.
 */
final class ImportSettings
{
    public const SECTION_HEADING       = 'heading';
    public const SECTION_CATEGORY_LIST = 'category_list';
    public const SECTION_CATEGORY_BLOG = 'category_blog';

    public function __construct(
        public readonly string $owner,
        public readonly string $repository,
        public readonly string $branch,
        public readonly string $manualBase,
        public readonly int $parentMenuItem,
        public readonly string $sectionMenuType,
        public readonly string $imagesFolder,
        public readonly string $imagesSubfolder,
        public readonly int $parentCategory,
        public readonly bool $sectionCategories,
        public readonly int $published,
        public readonly int $access,
        public readonly string $language,
        public readonly bool $updateExisting,
        public readonly bool $attribution,
        public readonly ConversionOptions $conversion
    ) {
    }

    /**
     * @param   array<string, mixed>  $params  The component's parameters
     */
    public static function fromParams(array $params): self
    {
        $sectionMenuType = (string) ($params['section_menu_type'] ?? self::SECTION_HEADING);

        if (!\in_array($sectionMenuType, [self::SECTION_HEADING, self::SECTION_CATEGORY_LIST, self::SECTION_CATEGORY_BLOG], true)) {
            $sectionMenuType = self::SECTION_HEADING;
        }

        $sectionCategories = (bool) ($params['section_categories'] ?? true);

        // A category menu item needs a category per section to point at.
        if (!$sectionCategories) {
            $sectionMenuType = self::SECTION_HEADING;
        }

        return new self(
            self::name((string) ($params['source_owner'] ?? 'ceford'), 'ceford'),
            self::name((string) ($params['source_repository'] ?? 'cefjdemos-data-jdm-help-en'), 'cefjdemos-data-jdm-help-en'),
            self::ref((string) ($params['source_branch'] ?? '2.0')),
            trim((string) ($params['manual_base'] ?? '')) ?: 'https://jdocmanual.org/en/',
            (int) ($params['parent_menu_item'] ?? 0),
            $sectionMenuType,
            self::folder((string) ($params['images_folder'] ?? '')),
            self::folder((string) ($params['images_subfolder'] ?? 'jdm-help')),
            (int) ($params['parent_category'] ?? 0),
            $sectionCategories,
            (int) ($params['published'] ?? 1) === 1 ? 1 : 0,
            max(1, (int) ($params['access'] ?? 1)),
            trim((string) ($params['language'] ?? '*')) ?: '*',
            ($params['on_existing'] ?? 'update') !== 'skip',
            (bool) ($params['attribution'] ?? true),
            ConversionOptions::fromParams($params)
        );
    }

    /**
     * Where the repository is downloaded from: one archive of the whole branch,
     * from GitHub's public download host. One GET, no API and so no token and
     * no API rate limit.
     */
    public function downloadUrl(): string
    {
        return \sprintf(
            'https://codeload.github.com/%s/%s/zip/%s',
            rawurlencode($this->owner),
            rawurlencode($this->repository),
            implode('/', array_map('rawurlencode', explode('/', $this->branch)))
        );
    }

    /**
     * The folder the images go to, relative to the site root: `images/` plus the
     * chosen folder plus the subfolder, e.g. `images/manuals/jdm-help`.
     */
    public function imageBase(): string
    {
        return implode('/', array_filter(['images', $this->imagesFolder, $this->imagesSubfolder], static fn (string $part): bool => $part !== ''));
    }

    /**
     * A page's address in the online manual, for the attribution line.
     */
    public function onlineUrl(string $sectionPath, string $slug): string
    {
        // Nested sections keep their slash: help/menu-items/articles/single-article.
        return rtrim($this->manualBase, '/') . '/jdocmanual?article=help/' . $sectionPath . '/' . $slug;
    }

    private static function name(string $value, string $default): string
    {
        $value = trim($value);

        return preg_match('/^[A-Za-z0-9._-]+$/', $value) === 1 ? $value : $default;
    }

    private static function ref(string $value): string
    {
        $value = trim($value, " \t\n\r/");

        return preg_match('#^[A-Za-z0-9._/-]+$#', $value) === 1 && !str_contains($value, '..') ? $value : '2.0';
    }

    /**
     * A relative folder without leading or trailing slashes, and without any
     * way to climb out of the images folder.
     */
    private static function folder(string $value): string
    {
        $parts = array_filter(
            explode('/', str_replace('\\', '/', trim($value))),
            static fn (string $part): bool => $part !== '' && $part !== '.' && $part !== '..'
        );

        $parts = array_map(static fn (string $part): string => (string) preg_replace('/[^A-Za-z0-9._-]/', '-', $part), $parts);

        return implode('/', $parts);
    }
}
