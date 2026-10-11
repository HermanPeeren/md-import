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
 * A section of the manual: a folder under `articles/`, holding pages and,
 * for "Menu Items", subsections of its own.
 *
 * It becomes a category (when section categories are on) and a menu heading.
 */
final class Section
{
    /** @var Page[] */
    public array $pages = [];

    /** @var Section[] */
    public array $children = [];

    /**
     * @param   string  $path      Relative to `articles/`, e.g. `menu-items/articles`
     * @param   string  $title     As folders.json names it
     * @param   int     $ordering  Position among its siblings, from menu.json
     */
    public function __construct(
        public readonly string $path,
        public readonly string $title,
        public readonly int $ordering
    ) {
    }

    /**
     * The last segment of the path, which is the alias it gets.
     */
    public function alias(): string
    {
        $parts = explode('/', $this->path);

        return (string) end($parts);
    }

    /**
     * The path of the section this one is inside, or '' at the top.
     */
    public function parentPath(): string
    {
        $position = strrpos($this->path, '/');

        return $position === false ? '' : substr($this->path, 0, $position);
    }

    /**
     * The key this section is remembered by between imports.
     */
    public function key(): string
    {
        return 'section:' . $this->path;
    }
}
