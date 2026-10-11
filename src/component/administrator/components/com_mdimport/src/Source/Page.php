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
 * One help page in the source repository: a Markdown file and where it sits.
 *
 * The slug is the file name without `.md`. It is what the manual's own links
 * point at (`jdocmanual?article=help/<section>/<slug>`), it is unique across the
 * whole repository, and it becomes the article's and menu item's alias.
 */
final class Page
{
    /**
     * @param   string  $slug         File name without extension, e.g. `articles-edit`
     * @param   string  $title        As menu.json names it
     * @param   string  $sectionPath  The section it belongs to, e.g. `menu-items/articles`
     * @param   string  $file         Path relative to the repository root, e.g. `articles/articles/articles-edit.md`
     * @param   int     $ordering     Position within its section, from menu.json
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $sectionPath,
        public readonly string $file,
        public readonly int $ordering
    ) {
    }

    /**
     * The key this page is remembered by between imports.
     */
    public function key(): string
    {
        return 'page:' . $this->file;
    }
}
