<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Mdimport\Administrator\Markdown;

\defined('_JEXEC') or die;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter as LeagueConverter;

/**
 * Markdown to HTML with league/commonmark, rewriting every link and image URL
 * on the way through.
 *
 * The rewriting happens on the parsed document, not on the HTML afterwards:
 * CommonMark knows exactly which strings are link destinations, and a regular
 * expression over HTML does not. The resolver is handed each destination and
 * returns the one to use - which is how `jdocmanual?article=help/x/y` becomes a
 * link to the imported article and `../../../en/images/x/y.png` an image under
 * the site's images folder.
 */
final class MarkdownConverter
{
    private LeagueConverter $converter;

    /** @var callable(string, string): string|null */
    private $resolver = null;

    public function __construct(private readonly ConversionOptions $options)
    {
        $this->converter = new LeagueConverter($this->environment());
    }

    /**
     * Convert Markdown to HTML.
     *
     * @param   string                                $markdown
     * @param   (callable(string, string): string)|null  $resolver  Called as ($url, 'link'|'image'), returns the URL to use
     */
    public function convert(string $markdown, ?callable $resolver = null): string
    {
        $this->resolver = $resolver;

        try {
            return (string) $this->converter->convert($markdown);
        } finally {
            $this->resolver = null;
        }
    }

    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        if ($this->resolver === null) {
            return;
        }

        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Image) {
                $node->setUrl(($this->resolver)($node->getUrl(), 'image'));
            } elseif ($node instanceof Link) {
                $node->setUrl(($this->resolver)($node->getUrl(), 'link'));
            }
        }
    }

    private function environment(): Environment
    {
        $options = $this->options;
        $config  = [
            'html_input'         => $options->htmlInput,
            'allow_unsafe_links' => $options->allowUnsafeLinks,
        ];

        if ($options->maxNestingLevel > 0) {
            $config['max_nesting_level'] = $options->maxNestingLevel;
        }

        if ($options->has('heading_permalink') || $options->has('table_of_contents')) {
            $config['heading_permalink'] = ['symbol' => '#', 'insert' => 'after', 'id_prefix' => '', 'fragment_prefix' => ''];
        }

        if ($options->has('table_of_contents')) {
            $config['table_of_contents'] = ['position' => 'top', 'min_heading_level' => 2, 'max_heading_level' => 3];
        }

        if ($options->has('external_link')) {
            $config['external_link'] = ['internal_hosts' => [], 'open_in_new_window' => true, 'nofollow' => '', 'noopener' => 'external'];
        }

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());

        if ($options->flavor === ConversionOptions::FLAVOR_GFM) {
            $environment->addExtension(new GithubFlavoredMarkdownExtension());
        } else {
            $this->addIf($environment, 'autolink', new AutolinkExtension());
            $this->addIf($environment, 'strikethrough', new StrikethroughExtension());
            $this->addIf($environment, 'table', new TableExtension());
            $this->addIf($environment, 'task_list', new TaskListExtension());
        }

        $this->addIf($environment, 'attributes', new AttributesExtension());
        $this->addIf($environment, 'description_list', new DescriptionListExtension());
        $this->addIf($environment, 'external_link', new ExternalLinkExtension());
        $this->addIf($environment, 'footnote', new FootnoteExtension());
        $this->addIf($environment, 'smart_punct', new SmartPunctExtension());

        if ($options->has('heading_permalink') || $options->has('table_of_contents')) {
            $environment->addExtension(new HeadingPermalinkExtension());
        }

        $this->addIf($environment, 'table_of_contents', new TableOfContentsExtension());

        // Before the extensions' own listeners - the table of contents among
        // them - so that they see rewritten URLs.
        $environment->addEventListener(DocumentParsedEvent::class, [$this, 'onDocumentParsed'], 100);

        return $environment;
    }

    private function addIf(Environment $environment, string $name, \League\CommonMark\Extension\ExtensionInterface $extension): void
    {
        if ($this->options->has($name)) {
            $environment->addExtension($extension);
        }
    }
}
