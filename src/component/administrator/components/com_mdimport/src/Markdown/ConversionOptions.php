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
 * How Markdown becomes HTML: the component's conversion options, as values.
 *
 * The same choices the Obix CommonMark content plugin offers, so somebody who
 * knows that plugin knows these: a flavour, what to do with raw HTML, whether
 * unsafe links are allowed, a nesting limit, and the optional extensions.
 */
final class ConversionOptions
{
    public const FLAVOR_COMMONMARK = 'commonmark';
    public const FLAVOR_GFM        = 'gfm';

    /** The extensions that can be switched on, by the name the options use. */
    public const EXTENSIONS = [
        'attributes',
        'autolink',
        'description_list',
        'external_link',
        'footnote',
        'heading_permalink',
        'smart_punct',
        'strikethrough',
        'table',
        'table_of_contents',
        'task_list',
    ];

    /** What GitHub-flavoured Markdown already includes. */
    public const GFM_EXTENSIONS = ['autolink', 'strikethrough', 'table', 'task_list'];

    /**
     * @param   string    $flavor            commonmark or gfm
     * @param   string    $htmlInput         allow, escape or strip
     * @param   bool      $allowUnsafeLinks  Allow javascript:, vbscript:, file: and data: links
     * @param   int       $maxNestingLevel   0 for no limit
     * @param   string[]  $extensions        Names from EXTENSIONS
     */
    public function __construct(
        public readonly string $flavor = self::FLAVOR_GFM,
        public readonly string $htmlInput = 'allow',
        public readonly bool $allowUnsafeLinks = false,
        public readonly int $maxNestingLevel = 0,
        public readonly array $extensions = []
    ) {
    }

    /**
     * From the component's parameters, as an array or anything with toArray().
     *
     * @param   array<string, mixed>  $params
     */
    public static function fromParams(array $params): self
    {
        $flavor = (string) ($params['md_flavor'] ?? self::FLAVOR_GFM);
        $flavor = \in_array($flavor, [self::FLAVOR_COMMONMARK, self::FLAVOR_GFM], true) ? $flavor : self::FLAVOR_GFM;

        $htmlInput = (string) ($params['md_html_input'] ?? 'allow');
        $htmlInput = \in_array($htmlInput, ['allow', 'escape', 'strip'], true) ? $htmlInput : 'allow';

        $extensions = $params['md_extensions'] ?? [];
        $extensions = \is_array($extensions) ? $extensions : explode(',', (string) $extensions);
        $extensions = array_values(array_intersect(self::EXTENSIONS, array_map('trim', array_map('strval', $extensions))));

        return new self(
            $flavor,
            $htmlInput,
            (bool) ($params['md_allow_unsafe_links'] ?? false),
            max(0, (int) ($params['md_max_nesting_level'] ?? 0)),
            $extensions
        );
    }

    public function has(string $extension): bool
    {
        if ($this->flavor === self::FLAVOR_GFM && \in_array($extension, self::GFM_EXTENSIONS, true)) {
            return true;
        }

        return \in_array($extension, $this->extensions, true);
    }
}
