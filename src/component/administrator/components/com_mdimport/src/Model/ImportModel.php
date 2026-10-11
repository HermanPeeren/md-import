<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Mdimport\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;
use Yepr\Component\Mdimport\Administrator\Import\ContentWriter;
use Yepr\Component\Mdimport\Administrator\Import\Downloader;
use Yepr\Component\Mdimport\Administrator\Import\Importer;
use Yepr\Component\Mdimport\Administrator\Import\ImportSettings;
use Yepr\Component\Mdimport\Administrator\Import\ItemMap;

/**
 * Puts the importer together from the component's options and the site, and
 * tells the screen what it needs to show.
 */
final class ImportModel extends BaseDatabaseModel
{
    private ?ItemMap $map = null;

    public function settings(): ImportSettings
    {
        return ImportSettings::fromParams(ComponentHelper::getParams('com_mdimport')->toArray());
    }

    public function importer(): Importer
    {
        self::loadCommonMark();

        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get(DatabaseInterface::class);

        return new Importer(
            $this->settings(),
            $this->map(),
            new ContentWriter($app, $db),
            // Joomla's own CA bundle, for the hosts - WAMP among them - whose
            // PHP has none configured and fails every https request without it.
            new Downloader(JPATH_LIBRARIES . '/vendor/composer/ca-bundle/res/cacert.pem'),
            rtrim((string) $app->get('tmp_path', JPATH_ROOT . '/tmp'), '/\\') . '/mdimport',
            JPATH_ROOT
        );
    }

    public function map(): ItemMap
    {
        return $this->map ??= new ItemMap(Factory::getContainer()->get(DatabaseInterface::class));
    }

    /**
     * Whether the library this package installs is there to convert with.
     */
    public static function commonMarkAvailable(): bool
    {
        return is_file(JPATH_LIBRARIES . '/yepr_commonmark/autoload.php');
    }

    /**
     * league/commonmark, from lib_yepr_commonmark.
     */
    public static function loadCommonMark(): void
    {
        if (!self::commonMarkAvailable()) {
            throw new \RuntimeException('The library lib_yepr_commonmark is not installed. Install the MD Import package again: it carries the library.');
        }

        $autoload = JPATH_LIBRARIES . '/yepr_commonmark/autoload.php';

        require_once $autoload;
    }
}
