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
 * Splits a Jdocmanual page into its metadata and its Markdown.
 *
 * Every page opens with an HTML comment holding JSON:
 *
 *   <!--
 *   {
 *     "source": "https://docs.joomla.org/Help4.x:Articles",
 *     "title": "Articles: Introduction",
 *     "description": "",
 *     "author": ""
 *   }
 *   -->
 *
 * Left in, it would pass through CommonMark as raw HTML and end up in every
 * article. Taken out, the description becomes the article's meta description.
 */
final class PageParser
{
    /**
     * @param   array<string, mixed>  $metadata
     */
    private function __construct(
        public readonly array $metadata,
        public readonly string $markdown
    ) {
    }

    public static function parse(string $contents): self
    {
        // A BOM would stop the header from being at the very start.
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $contents = str_replace("\r\n", "\n", $contents);

        if (preg_match('/\A\s*<!--(.*?)-->\s*/s', $contents, $match) !== 1) {
            return new self([], $contents);
        }

        $metadata = json_decode(trim($match[1]), true);

        if (!\is_array($metadata)) {
            // A comment, but not the metadata one. Leave it where it is.
            return new self([], $contents);
        }

        return new self($metadata, substr($contents, \strlen($match[0])));
    }

    public function string(string $key): string
    {
        $value = $this->metadata[$key] ?? '';

        return \is_scalar($value) ? trim((string) $value) : '';
    }
}
