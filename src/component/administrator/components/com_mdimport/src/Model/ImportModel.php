<?php

/**
 * @package     Mdimport
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Mdimport\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Yepr\Component\Mdimport\Administrator\Import\Importer;
use Yepr\Component\Mdimport\Administrator\Import\ImporterAwareInterface;
use Yepr\Component\Mdimport\Administrator\Import\ImporterAwareTrait;
use Yepr\Component\Mdimport\Administrator\Import\ImportSettings;
use Yepr\Component\Mdimport\Administrator\Import\ImportSettingsAwareInterface;
use Yepr\Component\Mdimport\Administrator\Import\ImportSettingsAwareTrait;
use Yepr\Component\Mdimport\Administrator\Import\ItemMap;
use Yepr\Component\Mdimport\Administrator\Import\ItemMapAwareInterface;
use Yepr\Component\Mdimport\Administrator\Import\ItemMapAwareTrait;
use Yepr\Component\Mdimport\Administrator\Service\Provider\Import;

/**
 * What the import screen and the import task need, as the component's MVC
 * factory hands it over: the settings, the item map and the importer.
 */
final class ImportModel extends BaseDatabaseModel implements
    ImportSettingsAwareInterface,
    ItemMapAwareInterface,
    ImporterAwareInterface
{
    use ImportSettingsAwareTrait;
    use ItemMapAwareTrait;
    use ImporterAwareTrait;

    public function settings(): ImportSettings
    {
        return $this->getImportSettings();
    }

    public function map(): ItemMap
    {
        return $this->getItemMap();
    }

    public function importer(): Importer
    {
        return $this->getImporter();
    }

    /**
     * Whether the library this package installs is there to convert with.
     */
    public static function commonMarkAvailable(): bool
    {
        return is_file(JPATH_LIBRARIES . Import::COMMONMARK);
    }
}
