<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Mdimport\Administrator\Markdown;

\defined('_JEXEC') or die;

/**
 * Decides where a link or image in a help page should point once imported.
 *
 * Three kinds of destination appear in the manual:
 *
 *  - `jdocmanual?article=help/<section>/<slug>#fragment` - another help page.
 *    It is resolved by slug, which is unique across the manual, to the
 *    imported article. A slug that was not imported (a page that has since
 *    been removed) goes to the online manual instead, so it still works.
 *  - `jdocmanual?article=user/...` and other manuals - not imported, so they
 *    go to the online manual.
 *  - `../../../en/images/<path>` - an image in the repository. It goes to where
 *    the import copied it.
 *
 * Everything else - absolute URLs, anchors, mailto: - is left alone.
 */
final class UrlResolver
{
    /**
     * @param   callable(string): ?string  $articleUrl  slug => URL of the imported article, or null
     * @param   string                     $imageBase   Site-relative folder the images were copied to, e.g. `images/help`
     * @param   string                     $manualBase  Where the online manual lives, e.g. `https://jdocmanual.org/en/`
     */
    public function __construct(
        private $articleUrl,
        private readonly string $imageBase,
        private readonly string $manualBase
    ) {
    }

    public function __invoke(string $url, string $kind): string
    {
        return $kind === 'image' ? $this->image($url) : $this->link($url);
    }

    public function link(string $url): string
    {
        $parsed = self::manualLink($url);

        if ($parsed === null) {
            // A link written as a relative path to an image, which happens.
            return self::repositoryImage($url) !== null ? $this->image($url) : $url;
        }

        [$manual, $slug, $fragment] = $parsed;

        if ($manual === 'help' && $slug !== '') {
            $local = ($this->articleUrl)($slug);

            if ($local !== null) {
                return $local . ($fragment !== '' ? '#' . $fragment : '');
            }
        }

        return rtrim($this->manualBase, '/') . '/' . ltrim(substr($url, (int) strpos($url, 'jdocmanual')), '/');
    }

    public function image(string $url): string
    {
        $relative = self::repositoryImage($url);

        if ($relative === null) {
            return $url;
        }

        return trim($this->imageBase, '/') . '/' . $relative;
    }

    /**
     * The manual, slug and fragment of a Jdocmanual link, or null for any other URL.
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    public static function manualLink(string $url): ?array
    {
        if (preg_match('#^(?:https?://[^/]+/[a-z-]+/)?/?jdocmanual\?article=([a-z0-9_-]+)/([^\#\s]*)(?:\#(.*))?$#i', trim($url), $m) !== 1) {
            return null;
        }

        $parts = explode('/', trim($m[2], '/'));

        return [strtolower($m[1]), (string) end($parts), $m[3] ?? ''];
    }

    /**
     * The path below `images/` of an image in the repository, or null when the
     * URL does not point into one.
     *
     * Pages refer to images relatively and in the online manual's layout -
     * `../../../en/images/articles/articles/01-articles-list.png` - so only the
     * part after the language's `images/` folder means anything here.
     */
    public static function repositoryImage(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $url) === 1) {
            return null;
        }

        if (preg_match('#^(?:\./|\.\./)*(?:[a-z]{2}(?:-[a-z]{2})?/)?images/(.+)$#i', $url, $m) !== 1) {
            return null;
        }

        $path = $m[1];

        // Nothing that climbs back out of the images folder.
        if (str_contains($path, '..')) {
            return null;
        }

        return $path;
    }
}
