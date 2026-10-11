<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Mdimport\Administrator\Service\Provider;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Yepr\Component\Mdimport\Administrator\Import\ContentWriter;
use Yepr\Component\Mdimport\Administrator\Import\Downloader;
use Yepr\Component\Mdimport\Administrator\Import\Importer;
use Yepr\Component\Mdimport\Administrator\Import\ImportSettings;
use Yepr\Component\Mdimport\Administrator\Import\ItemMap;
use Yepr\Component\Mdimport\Administrator\Markdown\MarkdownConverter;

/**
 * The import, as services: the one place they are constructed.
 *
 * Each is shared - one per request - and built the first time something asks
 * for it. The component's MVC factory hands them to models and controllers;
 * see `Service\MVCFactory`.
 */
final class Import implements ServiceProviderInterface
{
    /**
     * Where league/commonmark comes from on a site: the library this package installs.
     */
    public const COMMONMARK = '/yepr_commonmark/autoload.php';

    public function register(Container $container): void
    {
        $container->share(
            ImportSettings::class,
            static fn (): ImportSettings => ImportSettings::fromParams(ComponentHelper::getParams('com_mdimport')->toArray())
        );

        $container->share(
            ItemMap::class,
            static fn (Container $container): ItemMap => new ItemMap($container->get(DatabaseInterface::class))
        );

        $container->share(
            ContentWriter::class,
            static fn (Container $container): ContentWriter => new ContentWriter(
                Factory::getApplication(),
                $container->get(DatabaseInterface::class)
            )
        );

        // Joomla's own CA bundle, for the hosts - WAMP among them - whose PHP
        // has none configured and fails every https request without it.
        $container->share(
            Downloader::class,
            static fn (): Downloader => new Downloader(JPATH_LIBRARIES . '/vendor/composer/ca-bundle/res/cacert.pem')
        );

        $container->share(
            MarkdownConverter::class,
            static function (Container $container): MarkdownConverter {
                $autoload = JPATH_LIBRARIES . self::COMMONMARK;

                if (!is_file($autoload)) {
                    throw new \RuntimeException(
                        'The library lib_yepr_commonmark is not installed. Install the MD Import package again: it carries the library.'
                    );
                }

                require_once $autoload;

                return new MarkdownConverter($container->get(ImportSettings::class)->conversion);
            }
        );

        $container->share(
            Importer::class,
            static fn (Container $container): Importer => new Importer(
                $container->get(ImportSettings::class),
                $container->get(ItemMap::class),
                $container->get(ContentWriter::class),
                $container->get(Downloader::class),
                $container->get(MarkdownConverter::class),
                rtrim((string) Factory::getApplication()->get('tmp_path', JPATH_ROOT . '/tmp'), '/\\') . '/mdimport',
                JPATH_ROOT
            )
        );
    }
}
